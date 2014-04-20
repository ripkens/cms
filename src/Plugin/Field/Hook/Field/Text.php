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
namespace Field;

use Cake\Event\Event;
use Cake\Event\EventListener;
use QuickApps\Utility\HooktagTrait;
use Field\Utility\TextToolbox;

/**
 * Text Field Handler.
 *
 * This field allows to store text information, such as textboxes, textareas, etc.
 */
class Text implements EventListener {

	use HooktagTrait;

/**
 * Return a list of implemented events.
 *
 * Events names must be named as follow:
 *
 *     Field.<FieldHandlerName>.<Entity|Instance>.<eventName>
 *
 * Example:
 *
 *     Field.Text.Entity.edit
 *
 * @return array
 */
	public function implementedEvents() {
		return [
			// Related to Entity:
			'Field.Text.Entity.display' => 'display',
			'Field.Text.Entity.edit' => 'edit',
			'Field.Text.Entity.formatter' => 'formatter',
			'Field.Text.Entity.beforeFind' => 'beforeFind',
			'Field.Text.Entity.afterSave' => 'afterSave',
			'Field.Text.Entity.beforeValidate' => 'beforeValidate',
			'Field.Text.Entity.afterValidate' => 'afterValidate',
			'Field.Text.Entity.beforeDelete' => 'beforeDelete',
			'Field.Text.Entity.afterDelete' => 'afterDelete',

			// Related to Instance:
			'Field.Text.Instance.info' => 'info',
			'Field.Text.Instance.settings' => 'settings',
			'Field.Text.Instance.beforeAttach' => 'beforeAttach',
			'Field.Text.Instance.afterAttach' => 'afterAttach',
			'Field.Text.Instance.beforeDetach' => 'beforeDetach',
			'Field.Text.Instance.afterDetach' => 'afterDetach',
		];
	}

/**
 * Returns an array of information of this field.
 *
 * - `name`: string, Human readable name of this field. ex. `Selectbox`
 * - `description`: string, Something about what this field does or allows to do.
 * - `hidden`: true|false, If set to false users can not use this field via `Field UI`
 *
 * @param \Cake\Event\Event $event
 * @return array
 */
	public function info(Event $event) {
		return [
			'name' => __d('field', 'Text'),
			'description' => __d('field', 'Allow to store text data in database.'),
			'hidden' => false
		];
	}

/**
 * Defines how the field will actually display its contents
 * when rendering entities.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param array $options Additional array of options
 * @return string HTML representation of this field
 */
	public function display(Event $event, $field, $options = []) {
		$View = $event->subject;
		$value = $field->value;
		$value = TextToolbox::process($value, $field->metadata->settings->text_processing);
		$field->set('value', $value);
		return $View->element('Field.text_field_display', compact('field', 'options'));
	}

/**
 * Renders field in edit mode.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param array $options
 * @return string HTML containing from elements
 */
	public function edit(Event $event, $field, $options = []) {
		$View = $event->subject;
		return $View->element('Field.text_field_edit', compact('field', 'options'));
	}

/**
 * All fields should have a 'default' formatter.
 * Any number of other formatters can be defined as well.
 * It's nice for there always to be a 'plain' option
 * for the `value` value, but that is not required.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param array $options
 * @return string HTML
 */
    public function formatter(Event $event, $field, $options = []) {
		$View = $event->subject;
        return $View->element('Field.text_field_formatter', compact('field', 'options'));
    }

/**
 * Renders all the form elements to be used on the field settings form.
 * Field settings will be the same for all shared instances of the same field and should
 * define the way the value will be stored in the database.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param array $options
 * @return string HTML form elements for the settings page
 */
	public function settings(Event $event, $field, $options = []) {
		$View = $event->subject;
		return $View->element('Field.text_field_settings', compact('field', 'options'));
	}

/**
 * After each entity is saved.
 *
 * The options array contains the `post` key, which holds
 * all the information you need to update you field.
 *
 *     $options['post']
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param \Cake\ORM\Entity $entity The entity which started the event
 * @param array $options
 * @return void
 */
	public function afterSave(Event $event, $field, $entity, $options) {
		$value = $options['post'];
		$field->set('value', $value);
	}

/**
 * Before an entity is validated as part of save process.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param \Cake\ORM\Entity $entity The entity which started the event
 * @param array $options
 * @param \Cake\Validation\Validator $validator
 * @return boolean False will halt the save process
 */
	public function beforeValidate(Event $event, $field, $entity, $options, $validator) {
		if ($field->metadata->required) {
			$validator->allowEmpty(":{$field->name}", false, __d('field', 'Field required.'))
				->add(":{$field->name}", 'validateRequired', [
					'rule' => function ($value, $context) use ($field) {
						if ($field->metadata->settings->type === 'textarea') {
							return !empty(html_entity_decode(trim(strip_tags($value))));
						} else {
							return !empty(trim(strip_tags($value)));
						}
					},
					'message' => __d('field', 'Field required.'),
				]);
		} else {
			$validator->allowEmpty(":{$field->name}", true);
		}

		if (
			$field->metadata->settings->type === 'text' &&
			!empty($field->metadata->settings->max_len) &&
			$field->metadata->settings->max_len > 0
		) {
			$validator->add(":{$field->name}", 'validateLen', [
				'rule' => function ($value, $context) use ($field) {
					return strlen(trim($value)) <= $field->metadata->settings->max_len;
				},
				'message' => __d('field', 'Max. %s characters length.', $field->metadata->settings->max_len),
			]);
		}

		if (!empty($field->metadata->settings->validation_rule)) {
			if (!empty($field->metadata->settings->validation_message)) {
				$message = $this->hooktags($field->metadata->settings->validation_message);
			} else {
				$message = __d('field', 'Invalid field.', $field->label);
			}

			$validator->add(":{$field->name}", 'validateReg', [
				'rule' => function ($value, $context) use ($field) {
					return preg_match($field->metadata->settings->validation_rule, $value);
				},
				'message' => $message,
			]);
		}

		return true;
	}

/**
 * After an entity is validated as part of save process.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param \Cake\ORM\Entity $entity The entity which started the event
 * @param array $options
 * @param \Cake\Validation\Validator $validator [description]
 * @return boolean False will halt the save process
 */
	public function afterValidate(Event $event, $field, $entity, $options, $validator) {
		return true;
	}

/**
 * Before an entity is deleted from database.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param \Cake\ORM\Entity $entity The entity which started the event
 * @param array $options
 * @return boolean False will halt the delete process
 */
	public function beforeDelete(Event $event, $field, $entity, $options) {
		return true;
	}

/**
 * After an entity is deleted from database.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param array $options
 * @return void
 */
	public function afterDelete(Event $event, $field, $options) {
		return;
	}

/**
 * Before an new instance of this field is attached to a database table.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param \Cake\ORM\Table $table The table which $field is being attached to
 * @return boolean False will halt the attach process
 */
	public function beforeAttach(Event $event, $field, $table) {
		return true;
	}

/**
 * After an new instance of this field is attached to a database table.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param \Cake\ORM\Table $table The table which $field is attached to
 * @return void
 */
	public function afterAttach(Event $event, $field, $table) {
		return;
	}

/**
 * Before an instance of this field is detached from a database table.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param \Cake\ORM\Table $table The table which $field is attached to
 * @return boolean False will halt the detach process
 */
	public function beforeDetach(Event $event, $field, $table) {
		return;
	}

/**
 * After an instance of this field was detached from a database table.
 *
 * @param \Cake\Event\Event $event The event that was fired
 * @param \Field\Model\Entity\Field $field Field information
 * @param \Cake\ORM\Table $table The table which $field was attached to
 * @return void
 */
	public function afterDetach(Event $event, $field, $table) {
		return;
	}

}
