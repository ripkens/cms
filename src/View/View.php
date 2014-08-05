<?php
/**
 * Licensed under The GPL-3.0 License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since	 2.0.0
 * @author	 Christopher Castro <chris@quickapps.es>
 * @link	 http://www.quickappscms.org
 * @license	 http://opensource.org/licenses/gpl-3.0.html GPL-3.0 License
 */
namespace QuickApps\View;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Network\Session;
use Cake\View\View as CakeView;
use QuickApps\Utility\AlertTrait;
use QuickApps\Utility\DetectorTrait;
use QuickApps\Utility\HooktagTrait;
use QuickApps\Utility\HookTrait;
use QuickApps\Utility\ViewModeTrait;
use User\Error\UserNotLoggedInException;
use User\Model\Entity\User;

/**
 * QuickApps View class.
 *
 * Extends Cake's View class to adds some QuickAppsCMS's specific
 * functionalities such as alerts rendering, objects rendering, and more.
 */
class View extends CakeView {

	use AlertTrait;
	use DetectorTrait;
	use HooktagTrait;
	use HookTrait;
	use ViewModeTrait;

/**
 * True when the view has been rendered.
 *
 * Used to stop infinite loops when using render() method.
 *
 * @var boolean
 */
	protected $_hasRendered = false;

/**
 * Overrides Cake's view rendering method.
 * Allows the usage of `$this->render($someObject)` in views.
 *
 * **Example:**
 *
 *     // $node, instance of: Node\Model\Entity\Node
 *     $this->render($node);
 *     // $block, instance of: Block\Model\Entity\Block
 *     $this->render($block);
 *     // $field, instance of: Field\Model\Entity\Field
 *     $this->render($field);
 *
 * When rendering objects the `ObjectRender.<ClassName>` hook-callback is automatically fired.
 * For example, when rendering a node entity the following hook is fired asking for its HTML rendering:
 *
 *     // Will trigger: ObjectRender.QuickaApps\Node\Model\Entity\Node
 *     $someNode = TableRegistry::get('Nodes')->get(1);
 *     $this->render($someNode);
 *
 * It is not limited to Entity instances only, you can virtually define an `ObjectRender` for
 * any class name.
 *
 * You can pass an unlimited number of arguments to your `ObjectRender` as follow:
 *
 *     $this->render($someObject, arg_1, arg_2, ...., arg_n);
 *
 * Your ObjectRender may look as below:
 *
 *     public function renderMyObject(Event $event, $theObject, $arg_1, $arg_2, ..., $arg_n);
 *
 * @param mixed $view View file to render. Or an object to be rendered
 * @param mixed $layout Layout file to use when rendering view file. Or extra array of options
 * for object rendering
 * @return string HTML output of object-rendering or view file
 */
	public function render($view = null, $layout = null) {
		if (is_object($view)) {
			$className = get_class($view);
			$args = func_get_args();
			array_shift($args);
			$args = array_merge([$view], (array)$args); // [entity, options]
			$event = new Event("ObjectRender.{$className}", $this, $args);
			EventManager::instance()->dispatch($event);
			$html = $event->result;
		} else {
			$this->alter('View.render', $view, $layout);
			$this->Html->script('System.jquery.js', ['block' => true]);
			if (!$this->_hasRendered) {
				$this->_hasRendered = true;
				$this->_setTitle();
				$this->_setDescription();
				$html = parent::render($view, $layout);
			}
		}

		return $html;
	}

/**
 * Overrides Cake's `View::element()` method.
 *
 * @param string $name Name of template file in the/app/Template/Element/ folder,
 *   or `MyPlugin.template` to use the template element from MyPlugin. If the element
 *   is not found in the plugin, the normal view path cascade will be searched.
 * @param array $data Array of data to be made available to the rendered view (i.e. the Element)
 * @param array $options Array of options. Possible keys are:
 * - `cache` - Can either be `true`, to enable caching using the config in View::$elementCache. Or an array
 *   If an array, the following keys can be used:
 *   - `config` - Used to store the cached element in a custom cache configuration.
 *   - `key` - Used to define the key used in the Cache::write(). It will be prefixed with `element_`
 * - `callbacks` - Set to true to fire beforeRender and afterRender helper callbacks for this element.
 *   Defaults to false.
 * - `ignoreMissing` - Used to allow missing elements. Set to true to not trigger notices.
 * @return string Rendered Element
 */
	public function element($name, array $data = [], array $options = []) {
		$this->alter('View.element', $name, $data, $options);
		$html = parent::element($name, $data, $options);
		return $html;
	}

/**
 * Gets current logged in user as an entity.
 *
 * This method will throw when user is not logged in.
 * You must make sure user is logged in before using this method:
 *
 *     // in any view:
 *     if ($this->is('user.logged')) {
 *         $userName = $this->user()->name;
 *     }
 *
 * @return \User\Model\Entity\User
 * @throws \User\Error\UserNotLoggedInException
 */
	public function user() {
		if (!$this->is('user.logged')) {
			throw new UserNotLoggedInException(__d('user', 'View::user(), requires User to be logged in.'));
		}
		return new User((new Session())->read('user'));
	}

/**
 * Sets meta-description for layout.
 *
 * It sets `description_for_layout` view-variable, and appends meta-description tag to `meta` block.
 * It will try to extract meta-description from the Node being rendered (if not empty). Otherwise, site's
 * description will be used.
 *
 * @return void
 */
	protected function _setDescription() {
		if (empty($this->viewVars['description_for_layout'])) {
			$description = '';

			if (
				!empty($this->viewVars['node']) &&
				($this->viewVars['node'] instanceof \Node\Model\Entity\Node) &&
				!empty($this->viewVars['node']->description)
			) {
				$title = $this->viewVars['node']->description;
			} else {
				foreach ($this->viewVars as $var) {
					if (
						is_object($var) &&
						($var instanceof \Node\Model\Entity\Node) &&
						!empty($var->title)
					) {
						$title = $var->description;
						break;
					}
				}
			}

			$description = empty($description) ? Configure::read('QuickApps.variables.site_description') : $description;
			$this->assign('description', $description);
			$this->set('description_for_layout', $description);
			$this->append('meta', $this->Html->meta('description', $description));
		} else {
			$this->assign('description', $this->viewVars['description_for_layout']);
		}
	}

/**
 * Sets title for layout.
 *
 * It sets `title_for_layout` view variable, if no previous title was set on controller.
 * It will try to extract title from the Node being rendered (if not empty). Otherwise, site's
 * title will be used.
 *
 * @return void
 */
	protected function _setTitle() {
		if (empty($this->viewVars['title_for_layout'])) {
			$title = '';

			if (
				!empty($this->viewVars['node']) &&
				($this->viewVars['node'] instanceof \Node\Model\Entity\Node) &&
				!empty($this->viewVars['node']->title)
			) {
				$title = $this->viewVars['node']->title;
			} else {
				foreach ($this->viewVars as $var) {
					if (
						is_object($var) &&
						($var instanceof \Node\Model\Entity\Node) &&
						!empty($var->title)
					) {
						$title = $var->title;
						break;
					}
				}
			}

			$title = empty($title) ? Configure::read('QuickApps.variables.site_title') : $title;
			$this->assign('title', $title);
			$this->set('title_for_layout', $title);
		} else {
			$this->assign('title', $this->viewVars['title_for_layout']);
		}
	}

}
