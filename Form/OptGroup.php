<?php

namespace dokuwiki\plugin\data\Form;

class OptGroup extends \dokuwiki\Form\OptGroup
{
    protected $values = [];

    /**
     * Store the given values so they can be used during rendering
     *
     * This is intended to be only called from within DropdownElement::val()
     *
     * @param string[] $values the values to set
     * @return string[] the values that have been set (options exist)
     * @see DropdownElement::val()
     */
    public function storeValues($values)
    {
        $this->values = [];
        foreach ($values as $value) {
            if(isset($this->options[$value])) {
                $this->values[] = $value;
            }
        }

        return $this->values;
    }

    /**
     * @return string
     */
    protected function renderOptions()
    {
        $html = '';
        foreach ($this->options as $key => $val) {
            $selected = in_array((string)$key, $this->values) ? ' selected="selected"' : '';
            $attrs = '';
            if (!empty($val['attrs']) && is_array($val['attrs'])) {
                $attrs = buildAttributes($val['attrs']);
            }
            $html .= '<option' . $selected . ' value="' . hsc($key) . '" ' . $attrs . '>';
            $html .= hsc($val['label']);
            $html .= '</option>';
        }
        return $html;
    }
}
