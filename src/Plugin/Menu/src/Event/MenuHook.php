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
namespace Hook;

use Cake\Collection\Collection;
use Cake\Event\Event;
use Cake\Event\EventListener;
use Cake\ORM\TableRegistry;
use QuickApps\Utility\CacheTrait;

/**
 * Menu rendering dispatcher.
 *
 */
class MenuHook implements EventListener {

	use CacheTrait;

/**
 * Returns a list of hooks this Hook Listener is implementing. When the class
 * is registered in an event manager, each individual method will be associated with
 * the respective event.
 *
 * @return void
 */
	public function implementedEvents() {
		return [
			'Block.Menu.display' => 'displayBlock',
		];
	}

/**
 * Renders menu's associated block.
 *
 * You can define `specialized-renders` according to your needs as follow.
 * This method looks for specialized renders in the order described below, if one
 * is not found we look the next one, etc.
 *
 * ### Render menu per theme's region & view-mode
 * 
 *      render_menu_[region-name]_[view-mode]
 *
 * Renders the given block per theme's `region-name` + `view-mode` combination:
 *
 *     // render for menus in `left-sidebar` region when view-mode is `full`
 *     `render_menu_left-sidebar_full.ctp`
 *
 *     // render for menus in `left-sidebar` region when view-mode is `search-result`
 *     `render_menu_left-sidebar_search-result.ctp`
 *
 *     // render for menus in `footer` region when view-mode is `search-result`
 *     `render_menu_footer_search-result.ctp`
 *
 * ### Render menu per theme's region
 *
 *     render_menu_[region-name]
 *
 * Similar as before, but just per theme's `region` and any view-mode
 *
 *     // render for menus in `right-sidebar` region
 *     `render_menu_right-sidebar.ctp`
 *
 *     // render for menus in `left-sidebar` region
 *     `render_menu_left-sidebar.ctp`
 *
 * ### Default
 * 
 *     render_block.ctp
 *
 * This is the global render, if none of the above is found we try to use this last.
 *
 * ---
 *  
 * NOTE: Please note the difference between "_" and "-"
 * 
 * @param \Cake\Event\Event $event
 * @param \Block\Model\Entity\Block $block The block being rendered
 * @param array $options Array of options for BlockHelper::render() method
 * @return array
 */
	public function displayBlock(Event $event, $block, $options) {
		$View = $event->subject;
		$viewMode = $View->inUseViewMode();
		// avoid scanning file system every time a block is being rendered
		$cacheKey = "displayBlock_{$block->region->region}_{$viewMode}";
		$cache = static::_cache($cacheKey);
		if ($cache !== null) {
			$element = $cache;
		} else {
			$try = [
				"Menu.render_menu_{$block->region->region}_{$viewMode}",
				"Menu.render_menu_{$block->region->region}",
				'Menu.render_menu'
			];

			foreach ($try as $possible) {
				if ($View->elementExists($possible)) {
					$element = static::_cache($cacheKey, $possible);
					break;
				}
			}
		}

		$menu = TableRegistry::get('Menu.Menus')
			->find()
			->contain(['Blocks'])
			->where(['Menus.id' => $block->delta])
			->first();
		$links = TableRegistry::get('Menu.MenuLinks')
			->find('threaded')
			->where(['menu_id' => $menu->id])
			->order(['lft' => 'ASC']);
		$menu->set('links', $links);

		return $View->element($element, compact('menu', 'options'));
	}

}
