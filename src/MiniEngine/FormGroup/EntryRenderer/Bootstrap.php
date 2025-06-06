<?php

class MiniEngine_FormGroup_EntryRenderer_Bootstrap
{
    public static function render($entry)
    {
        $type = $entry->options['type'] ?? 'text';
        if (in_array($type, [
            'text', 'password',
            'email', 'url', 'number',
            'datetime-local', 'date', 'time',
        ])) {
            return self::renderText($type, $entry);
        } elseif ('textarea' == $type) {
            return self::renderTextarea($entry);
        } elseif ('checkbox' == $type) {
            return self::renderCheckbox($entry);
        } elseif ('radio' == $type) {
            return self::renderRadio($entry);
        } elseif ('select' == $type) {
            return self::renderSelect($entry);
        }
        throw new Exception("Unknown type {$type}");
    }

    public static function getEntryKey($entry)
    {
        return get_class($entry->group) . '_' . $entry->name;
    }

    public static function renderStart($entry)
    {
        ob_start();
?>
<div class="mb-3 row <?= $entry->hasError() ? 'has-validation' : '' ?>">
    <?php if ($entry->options['label'] ?? false) { ?>
    <div class="col-auto">
    <label for="<?= htmlspecialchars(self::getEntryKey($entry)) ?>"><?= htmlspecialchars($entry->options['label']) ?>
        <?php if ($entry->options['required'] ?? false) { ?>
        <span class="text-danger">*</span>
        <?php } ?>
    </label>
    </div>
    <?php } ?>
    <div class="col-auto">
<?php
        return ob_get_clean();
    }

    public static function renderEnd($entry)
    {
        ob_start();
?>
    </div>
    <?php if ($entry->hasError()) { ?>
    <div class="invalid-feedback">
    <?= htmlspecialchars($entry->group->getEntryError($entry->name)) ?>
    </div>
    <?php } ?>
</div>
<?php
        return ob_get_clean();
    }

    public static function renderText($type, $entry)
    {
        ob_start();
        echo self::renderStart($entry);
?>
    <input type="<?= $type ?>"
        name="<?= htmlspecialchars($entry->options['input_name'] ?? $entry->name) ?>"
        id="<?= htmlspecialchars(self::getEntryKey($entry)) ?>" 
        class="form-control <?= $entry->hasError() ? 'is-invalid' : '' ?>"
        <?php if ($entry->options['placeholder'] ?? false) { ?>
        placeholder="<?= htmlspecialchars($entry->options['placeholder']) ?>"
        <?php } ?>
        <?php if ($entry->options['required'] ?? false) { ?>
        required
        <?php } ?>

        <?php foreach (['min', 'max', 'step'] as $attr) { ?>
          <?php if (isset($entry->options[$attr])) { ?>
            <?= htmlspecialchars($attr) ?>="<?= htmlspecialchars($entry->options[$attr]) ?>"
          <?php } ?>
        <?php } ?>
        value="<?= htmlspecialchars($entry->getEntryValue()) ?>"
        />
<?php
        echo self::renderEnd($entry);
        return ob_get_clean();
    }

    public static function renderTextarea($entry)
    {
        ob_start();
        echo self::renderStart($entry);
?>
    <textarea name="<?= htmlspecialchars($entry->options['input_name'] ?? $entry->name) ?>"
        id="<?= htmlspecialchars(self::getEntryKey($entry)) ?>"
        class="form-control <?= $entry->hasError() ? 'is-invalid' : '' ?>"
        <?php if ($entry->options['placeholder'] ?? false) { ?>
        placeholder="<?= htmlspecialchars($entry->options['placeholder']) ?>"
        <?php } ?>
        <?php if ($entry->options['required'] ?? false) { ?>
        required
        <?php } ?>
    ><?= htmlspecialchars($entry->getEntryValue()) ?></textarea>
<?php
        echo self::renderEnd($entry);
        return ob_get_clean();
    }

    public static function renderCheckbox($entry)
    {
        ob_start();
        echo self::renderStart($entry);
?>
<?php foreach ($entry->options['options'] as $value => $label) { ?>
<div class="form-check">
    <input type="checkbox"
        name="<?= htmlspecialchars($entry->options['input_name'] ?? $entry->name) ?>[]"
        id="<?= htmlspecialchars(self::getEntryKey($entry) . '_' . $value) ?>"
        value="<?= htmlspecialchars($value) ?>"
        class="form-check-input <?= $entry->hasError() ? 'is-invalid' : '' ?>"
        <?php if (is_array($entry->getEntryValue()) and in_array($value, $entry->getEntryValue())) { ?> checked <?php } ?>
    />
    <label class="form-check-label" for="<?= htmlspecialchars(self::getEntryKey($entry) . '_' . $value) ?>">
        <?= htmlspecialchars($label) ?>
    </label>
</div>
<?php } ?>
<?php
        echo self::renderEnd($entry);
        return ob_get_clean();
    }

    public static function renderRadio($entry)
    {
        ob_start();
        $final_option = strval(array_key_last($entry->options['options']));
        echo self::renderStart($entry);
?>
<?php foreach ($entry->options['options'] as $value => $label) { ?>
<?php $value = strval($value); // Ensure value is a string ?>
<div class="form-check">
    <input type="radio"
        name="<?= htmlspecialchars($entry->options['input_name'] ?? $entry->name) ?>"
        id="<?= htmlspecialchars(self::getEntryKey($entry) . '_' . $value) ?>"
        value="<?= htmlspecialchars($value) ?>"
        class="form-check-input <?= $entry->hasError() ? 'is-invalid' : '' ?>"
        <?php if ($value === $entry->getEntryValue()) { ?> checked <?php } ?>
    />
    <label class="form-check-label" for="<?= htmlspecialchars(self::getEntryKey($entry) . '_' . $value) ?>">
        <?= htmlspecialchars($label) ?>
    </label>
    <?php if ($entry->hasError() and $final_option === $value) { ?>
    <div class="invalid-feedback">
        <?= htmlspecialchars($entry->group->getEntryError($entry->name)) ?>
    </div>
    <?php } ?>
</div>
<?php } ?>
<?php
        echo self::renderEnd($entry);
        return ob_get_clean();
    }

    public static function renderSelect($entry)
    {
        ob_start();
        echo self::renderStart($entry);
?>
    <select name="<?= htmlspecialchars($entry->options['input_name'] ?? $entry->name) ?>"
        id="<?= htmlspecialchars(self::getEntryKey($entry)) ?>"
        class="form-select <?= $entry->hasError() ? 'is-invalid' : '' ?>"
        <?php if ($entry->options['required'] ?? false) { ?>
        required
        <?php } ?>>
        <?php foreach ($entry->options['options'] as $value => $label) { ?>
        <option value="<?= htmlspecialchars($value) ?>" 
            <?php if ($value == $entry->getEntryValue()) { ?> selected <?php } ?>>
            <?= htmlspecialchars($label) ?>
        </option>
        <?php } ?>
    </select>
<?php
        echo self::renderEnd($entry);
        return ob_get_clean();
    }
}
