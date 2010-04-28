<?php

if (file_exists(DOKU_PLUGIN . 'bureaucracy/fields/field.php')) {
    require_once DOKU_PLUGIN . 'bureaucracy/fields/field.php';

    class syntax_plugin_bureaucracy_field_dataplugin extends syntax_plugin_bureaucracy_field {

        function __construct($syntax_plugin, $args) {
            $dthlp =& plugin_load('helper', 'data');
            if(!$dthlp) msg('Loading the data helper failed. Make sure the data plugin is installed.',-1);

            $this->init($syntax_plugin, $args);
            $n_args = array();
            foreach ($args as $arg) {
                if ($arg[0] !== '_') {
                    $n_args[] = $arg;
                    continue;
                }
                $datatype = $dthlp->_column($arg);
                $datatype['basetype'] = $datatype['type'];
                $datatype['type'] = isset($datatype['origtype']) ? $datatype['origtype'] : $datatype['type'];
            }
            $this->standardArgs($n_args);

            if (isset($datatype['enum'])) {
                $values = preg_split('/\s*,\s*/', $datatype['enum']);
                if (!$datatype['multi'] && $this->opt['optional']) array_unshift($values, '');
                $content = form_makeListboxField('@@NAME@@', $values,
                                                 '@@VALUE@@', '@@LABEL@@', '', '', ($datatype['multi'] ? array('multiple' => 'multiple'): array()));
            } else {
                $classes = 'data_type_' . $datatype['type'] . ($datatype['multi'] ? 's' : '') .  ' ' .
                           'data_type_' . $datatype['basetype'] . ($datatype['multi'] ? 's' : '');
                $content = form_makeTextField('@@NAME@@', '@@VALUE@@', '@@LABEL@@', '', '@@CLASS@@ ' . $classes);

            }
            $this->tpl = $content;
        }

    }
}
