<?php

if (file_exists(DOKU_PLUGIN . 'bureaucracy/fields/field.php')) {
    require_once DOKU_PLUGIN . 'bureaucracy/fields/field.php';

    class syntax_plugin_bureaucracy_field_dataplugin extends syntax_plugin_bureaucracy_field {

        function __construct($args) {
            $dthlp =& plugin_load('helper', 'data');
            if(!$dthlp) msg('Loading the data helper failed. Make sure the data plugin is installed.',-1);

            $this->init($args);
            $n_args = array();
            foreach ($args as $arg) {
                if ($arg[0] !== '_') {
                    $n_args[] = $arg;
                    continue;
                }
                $datatype = $dthlp->_column($arg);
                if (is_array($datatype['type'])) {
                    $datatype['basetype'] = $datatype['type']['type'];
                    $datatype['enum'] = $datatype['type']['enum'];
                    $datatype['type'] = $datatype['origtype'];
                } else {
                    $datatype['basetype'] = $datatype['type'];
                }
            }
            $this->standardArgs($n_args);

            if (isset($datatype['enum'])) {
                $values = preg_split('/\s*,\s*/', $datatype['enum']);
                if (!$datatype['multi'] && $this->opt['optional']) array_unshift($values, '');
                $this->opt['args'] = $values;
                $this->additional = ($datatype['multi'] ? array('multiple' => 'multiple'): array());
            } else {
                $classes = 'data_type_' . $datatype['type'] . ($datatype['multi'] ? 's' : '') .  ' ' .
                           'data_type_' . $datatype['basetype'] . ($datatype['multi'] ? 's' : '');
                $content = form_makeTextField('@@NAME@@', '@@VALUE@@', '@@LABEL@@', '', '@@CLASS@@ ' . $classes);

                $this->tpl = $content;
            }
        }

        function render($params, $form) {
            if (isset($this->tpl)) {
                parent::render($params, $form);
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

                $form->addElement(call_user_func_array('form_makeListboxField',
                                                       $this->_parse_tpl(array('@@NAME@@[]',
                                                        $params['args'], $params['value'],
                                                        '@@LABEL@@', '', '@@CLASS@@', $this->additional),
                                                        $params)));
            }
        }

        function handle_post($value) {
            if (is_array($value)) {
                $value = join(', ', $value);
            }

            return parent::handle_post($value);
        }

    }
}
