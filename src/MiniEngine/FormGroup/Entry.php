<?php

class MiniEngine_FormGroup_Entry
{
    public $group;
    public $name;
    public $options;

    public function __construct($group, $name, $options)
    {
        $this->group = $group;
        $this->name = $name;
        $this->options = $options;
    }

    public function __toString()
    {
        return $this->group->render($this);
    }

    public function getEntryValue()
    {
        return $this->group->getEntryValue($this->name);
    }

    public function hasError()
    {
        return $this->group->getEntryError($this->name);
    }

    public function check($value)
    {
        if ($this->options['required'] ?? false) {
            if (is_null($value)) {
                throw new Exception("Field {$this->name} is required");
            }
        }
        if ($this->options['type'] == 'email') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Field {$this->name} must be a valid email address");
            }
        }
        return $value;
    }
}
