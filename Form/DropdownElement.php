<?php

namespace dokuwiki\plugin\data\Form;

use dokuwiki\Form\InputElement;
use dokuwiki\Form\OptGroup;

/**
 * Overrides some methods in parent in order to add not yet supported
 * multivalue capabilities.
 */
class DropdownElement extends \dokuwiki\Form\DropdownElement
{
    /** @var string[] the currently set values */
    protected $values = [];

    /** @var \dokuwiki\plugin\data\Form\OptGroup[] */
    protected $optGroups = [];


    /**
     * Override the parent constructor because it instantiates an OptGroup
     * which does not handle multivalues
     *
     * @param string $name
     * @param array $options
     * @param string $label
     */
    public function __construct($name, $options, $label = '')
    {
        InputElement::__construct('dropdown', $name, $label);
        $this->rmattr('type');
        $this->optGroups[''] = new \dokuwiki\plugin\data\Form\OptGroup(null, $options);
        $this->val('');
    }

    /**
     * Adds multivalue capabilities
     *
     * @param array $value
     * @return DropdownElement|string|array
     */
    public function val($value = null)
    {
        // getter
        if ($value === null) {
            if (isset($this->attributes['multiple'])) {
                return $this->values;
            } else {
                return $this->values[0];
            }
        }

        // setter
        $this->values = $this->setValuesInOptGroups((array)$value);
        if (!$this->values) {
            // unknown value set, select first option instead
            $this->values = $this->setValuesInOptGroups((array)$this->getFirstOptionKey());
        }

        return $this;
    }

    /**
     * Skips over parent's \InvalidArgumentException thrown for 'multiple'
     *
     * @param $name
     * @param $value
     * @return DropdownElement|string
     */
    public function attr($name, $value = null)
    {
        return InputElement::attr($name, $value);
    }

    /**
     * Returns the first option's key
     *
     * @return string
     */
    protected function getFirstOptionKey()
    {
        $options = $this->options();
        if (!empty($options)) {
            $keys = array_keys($options);
            return (string)array_shift($keys);
        }
        foreach ($this->optGroups as $optGroup) {
            $options = $optGroup->options();
            if (!empty($options)) {
                $keys = array_keys($options);
                return (string)array_shift($keys);
            }
        }

        return ''; // should not happen
    }

    /**
     * Set the value in the OptGroups, including the optgroup for the options without optgroup.
     *
     * @param string[] $values The values to be set
     * @return string[] The values actually set
     */
    protected function setValuesInOptGroups($values)
    {
        $valueset = [];

        /** @var \dokuwiki\plugin\data\Form\OptGroup $optGroup */
        foreach ($this->optGroups as $optGroup) {
            $found = $optGroup->storeValues($values);
            $values = array_diff($values, $found);
            $valueset = array_merge($valueset, $found);
        }

        return $valueset;
    }

    /**
     * @inheritDoc
     */
    protected function mainElementHTML()
    {
        $attr = $this->attrs();
        if (isset($attr['multiple'])) {
            // use array notation when multiple values are allowed
            $attr['name'] .= '[]';
        } elseif ($this->useInput) {
            // prefilling is only supported for non-multi fields
            $this->prefillInput();
        }

        unset($attr['selected']);

        $html = '<select ' . buildAttributes($attr) . '>';
        $html = array_reduce(
            $this->optGroups,
            function ($html, OptGroup $optGroup) {
                return $html . $optGroup->toHTML();
            },
            $html
        );
        $html .= '</select>';

        return $html;
    }
}
