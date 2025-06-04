<?php

class MiniEngine_FormGroup
{
    public $_entries = [];
    protected $_render = 'MiniEngine_FormGroup_EntryRenderer_Bootstrap';
    protected $_data = null;
    protected $_errors = null;
    public $_parent_group = null;
    public $_parent_group_options = null;

    public function init()
    {
        throw new Exception('FormGroup::init() must be overridden in a subclass');
    }

    public function __set($name, $options)
    {
        if (!is_null($this->_parent_group)) {
            $options['input_name'] = "{$this->_parent_group_options['group']}[{$name}]";
            $name = $this->_parent_group_options['group'] . '.' . $name;
            $this->_parent_group->_entries[$name] = new MiniEngine_FormGroup_Entry($this->_parent_group, $name, $options);
        } else {
            $this->_entries[$name] = new MiniEngine_FormGroup_Entry($this, $name, $options);
        }
    }

    protected static $_groups = [];

    public static function getGroup($class = null)
    {
        if (is_null($class)) {
            $class = get_called_class();
        }
        if (self::$_groups[$class] ?? false) {
            return self::$_groups[$class];
        }
        if (!class_exists($class)) {
            throw new Exception("MiniEngine_FormGroup {$class} doesn't exist");
        }
        self::$_groups[$class] = new $class();
        self::$_groups[$class]->init();
        return self::$_groups[$class];
    }

    public static function __callStatic($name, $args)
    {
        $group = self::getGroup();
        return $group->e($name, $args[0] ?? null);
    }

    public static function e($name, $args = null)
    {
        $group = self::getGroup();
        if (!($group->_entries[$name] ?? false)) {
            $group_class = get_class($group);
            throw new Exception("Element {$name} not found in group {$group_class}");
        }
        if (is_null($args)) {
            return $group->_entries[$name];
        }
        return $group->_entries[$name]->clone($args);
    }

    public function render($entry)
    {
        $renderer_class = $this->_render;
        return call_user_func([$renderer_class, 'render'], $entry);
    }

    public static function setData($data)
    {
        $group = self::getGroup();
        $group->_data = $data;
    }

    public static function getEntryValue($name)
    {
        $group = self::getGroup();
        if (!($group->_entries[$name] ?? false)) {
            throw new Exception("Entry {$name} not found in group");
        }
        return self::searchData($group->_data, $name);
    }

    public static function searchData($input_data, $k)
    {
        if (strpos($k, '.') === false) {
            return $input_data[$k] ?? null;
        }

        $keys = explode('.', $k);
        if (!($input_data[$keys[0]] ?? false)) {
            return null;
        }
        return self::searchData($input_data[$keys[0]], implode('.', array_slice($keys, 1)));
    }

    public static function checkData($input_data, &$errors = null)
    {
        $group = self::getGroup();
        if (is_null($errors)) {
            $errors = [];
        }
        $output_data = [];
        foreach ($group->_entries as $k => $entry) {
            try {
                $value = $entry->check(self::searchData($input_data, $k));
                if ($value !== null) {
                    if (strpos($k, '.') !== false) {
                        $keys = explode('.', $k);
                        if (!isset($output_data[$keys[0]])) {
                            $output_data[$keys[0]] = [];
                        }
                        $output_data[$keys[0]][implode('.', array_slice($keys, 1))] = $value;
                    } else {
                        // If no dot notation, just set the value directly
                        $output_data[$k] = $value;
                    }
                }
            } catch (Exception $e) {
                $errors[$k] = $e->getMessage();
            }
        }
        return $output_data;
    }

    public static function setErrors($errors)
    {
        $group = self::getGroup();
        $group->_errors = $errors;
    }

    public static function getEntryError($name)
    {
        $group = self::getGroup();
        if (!($group->_entries[$name] ?? false)) {
            throw new Exception("Entry {$name} not found in group");
        }
        if (!($group->_errors[$name] ?? false)) {
            return null;
        }
        return $group->_errors[$name];
    }

    public function setParentGroup($parent_group, $options)
    {
        $this->_parent_group = $parent_group;
        $this->_parent_group_options = $options;
    }

    public static function child_form($form_class, $options)
    {
        $group = self::getGroup();
        if (!class_exists($form_class)) {
            throw new Exception("Form {$form_class} doesn't exist");
        }
        $child_form = new $form_class();
        $child_form->setParentGroup($group, $options);
        $child_form->init();
    }
}
