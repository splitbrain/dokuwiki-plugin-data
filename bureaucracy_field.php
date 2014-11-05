<?php

if (file_exists(DOKU_PLUGIN . 'bureaucracy/fields/field.php')) {
    require_once DOKU_PLUGIN . 'bureaucracy/fields/field.php';

    /**
     * Class syntax_plugin_bureaucracy_field_dataplugin
     *
     * Create a field with properties defined by given type alias
     * Mostly this is a single line input field, but for enum type a select field.
     */
    class syntax_plugin_bureaucracy_field_dataplugin extends syntax_plugin_bureaucracy_field {

        private $args;
        private $additional;

        /**
         * Arguments:
         *  - cmd
         *  - label
         *  - _typealias (optional)
         *  - ^ (optional)
         *
         * @param array $args The tokenized definition, only split at spaces
         */
        public function __construct($args) {
            $this->init($args);
            $n_args = array();
            $this->args = array();
            foreach ($args as $arg) {
                if ($arg[0] !== '_') {
                    $n_args[] = $arg;
                    continue;
                }
                $this->args[] = $arg;
            }
            $this->standardArgs($n_args);

        }

        /**
         * Prepare
         *
         * @param array $args data plugin related field arguments
         */
        private function prepareColumns($args) {
            /** @var helper_plugin_data $dthlp */
            $dthlp = plugin_load('helper', 'data');
            if(!$dthlp) msg('Loading the data helper failed. Make sure the data plugin is installed.',-1);

            foreach ($args as $arg) {
                $arg = $this->replaceTranslation($arg);
                $datatype = $dthlp->_column($arg);
                if (is_array($datatype['type'])) {
                    $datatype['basetype'] = $datatype['type']['type'];
                    $datatype['enum'] = $datatype['type']['enum'];
                    $datatype['type'] = $datatype['origtype'];
                } else {
                    $datatype['basetype'] = $datatype['type'];
                }
            }
            $datatype['title'] = '@@DISPLAY@@';
            if (isset($datatype['enum'])) {
                $values = preg_split('/\s*,\s*/', $datatype['enum']);
                if (!$datatype['multi'] && $this->opt['optional']) array_unshift($values, '');
                $this->opt['args'] = $values;
                $this->additional = ($datatype['multi'] ? array('multiple' => 'multiple'): array());
            } else {
                $classes = 'data_type_' . $datatype['type'] . ($datatype['multi'] ? 's' : '') .  ' ' .
                           'data_type_' . $datatype['basetype'] . ($datatype['multi'] ? 's' : '');
                $content = form_makeTextField('@@NAME@@', '@@VALUE@@', '@@DISPLAY@@', '@@ID@@', '@@CLASS@@ ' . $classes);

                $this->tpl = $content;
            }
            if (!isset($this->opt['display'])) {
                $this->opt['display'] = $this->opt['label'];
            }

        }

        /**
         * Render the field as XHTML
         *
         * Creates a single line input field or select field
         *
         * @params array     $params Additional HTML specific parameters
         * @params Doku_Form $form   The target Doku_Form object
         * @params int       $formid unique identifier of the form which contains this field
         */
        public function renderfield($params, Doku_Form $form, $formid) {
            $this->prepareColumns($this->args);

            if (isset($this->tpl)) {
                parent::renderfield($params, $form, $formid);
            } else {
                // Is an enum type, otherwise $this->tpl would be set in __construct
                $this->_handlePreload();
                if(!$form->_infieldset){
                    $form->startFieldset('');
                }
                if ($this->error) {
                    $params['class'] = 'bureaucracy_error';
                }
                $params = array_merge($this->opt, $params);
                $params['value'] = preg_split('/\s*,\s*/', $params['value'], -1, PREG_SPLIT_NO_EMPTY);
                if (count($params['value']) === 0) {
                    $params['value'] = $params['args'][0];
                }
                if(!isset($this->opt['optional'])) {
                    $this->additional['required'] = 'required';
                }

                $form->addElement(call_user_func_array('form_makeListboxField',
                                                       $this->_parse_tpl(
                                                           array(
                                                               '@@NAME@@[]',
                                                               $params['args'],
                                                               $params['value'],
                                                               '@@DISPLAY@@',
                                                               '',
                                                               '@@CLASS@@',
                                                               $this->additional
                                                           ),
                                                           $params
                                                       )));
            }
        }

        /**
         * Handle a post to the field
         *
         * Accepts and validates a posted value.
         *
         * @param string $value The passed value or array or null if none given
         * @param syntax_plugin_bureaucracy_field[] $fields (reference) form fields (POST handled upto $this field)
         * @param int    $index  index number of field in form
         * @param int    $formid unique identifier of the form which contains this field
         * @return bool Whether the passed value is valid
         */
        public function handle_post($value, &$fields, $index, $formid) {
            if (is_array($value)) {
                $value = join(', ', $value);
            }

            return parent::handle_post($value, $fields, $index, $formid);
        }

        /**
         * Replace the translation placeholders
         *
         * @param string $string
         * @return string parsed string
         */
        private function replaceTranslation($string) {
            global $ID;
            global $conf;

            $lang = $conf['lang'];
            $trans = '';

            /** @var helper_plugin_translation $translationPlugin */
            $translationPlugin = plugin_load('helper', 'translation');
            if ($translationPlugin) {
                $trans = $translationPlugin->getLangPart($ID);
                $lang = $translationPlugin->realLC('');
            }

            $string = str_replace('@LANG@', $lang, $string);
            return str_replace('@TRANS@', $trans, $string);
        }
    }
}
