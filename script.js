/**
 * Init datepicker for all date fields
 */
jQuery(function () {
    jQuery('.data_type_dt input').datepicker({
        dateFormat: "yy-mm-dd",
        changeMonth: true,
        changeYear: true
    });
});

/**
 * Init autocompletion for all page alias fields
 *
 * @author Adrian Lang <lang@cosmocode.de>
 * @author Gerrit Uitslag <klapinklapin@gmail.com>
 */
jQuery(function () {
    /**
     * Returns aliastype of field
     *
     * @param {jQuery} $input
     * @return {String} aliastype of the input
     */
    function getAliastype($input) {
        var classes = $input.parent().attr('class').split(' '),
            multi = false,
            aliastype = 'data_type_page';

        jQuery.each(classes, function (i, cls) {
            //skip base type
            if (cls == 'data_type_page' || cls == 'data_type_pages') {
                multi = cls.substr(cls.length-1, 1) == 's';
                return true;
            }
            //only data types, no other classes
            if (cls.substr(0, 10) == 'data_type_') {
                aliastype = cls;
            }
        });
        //return singular aliastype
        return (multi ? aliastype.substr(0, aliastype.length - 1) : aliastype);
    }

    /**
     * Ajax request for user suggestions
     *
     * @param {Object} request object, with single 'term' property
     * @param {Function} response callback, argument: the data to suggest to the user.
     * @param {Function} getTerm callback, argument: the request Object, returns: search term
     * @param aliastype
     */
    function ajaxsource(request, response, getTerm, aliastype) {
        jQuery.getJSON(
            DOKU_BASE + 'lib/exe/ajax.php', {
                call: 'data_page',
                aliastype: aliastype,
                search: getTerm(request)
            }, function (data) {
                response(jQuery.map(data, function (name, id) {
                    return {
                        label: name + ' (' + id + ')',
                        value: id
                    }
                }))
            }
        );
    }

    function split(val) {
        return val.split(/,\s*/);
    }

    function extractLast(term) {
        return split(term).pop();
    }


    /**
     * pick one user
     */
    jQuery(".data_type_page input").autocomplete({
        source: function (request, response) {
            ajaxsource(
                request,
                response,
                function (req) {
                    return req.term;
                },
                getAliastype(this.element)
            );
        }
    });

    /**
     * pick one or more users
     */
    jQuery(".data_type_pages input")
        // don't navigate away from the field on tab when selecting an item
        .bind("keydown", function (event) {
            if (event.keyCode === jQuery.ui.keyCode.TAB &&
                jQuery(this).data("ui-autocomplete").menu.active) {
                event.preventDefault();
            }
        })
        .autocomplete({
            minLength: 0,
            source: function (request, response) {
                ajaxsource(
                    request,
                    response,
                    function (req) {
                        return extractLast(req.term);
                    },
                    getAliastype(this.element)
                );
            },
            search: function () {
                // custom minLength
                var term = extractLast(this.value);
                if (term.length < 2) {
                    return false;
                }
                return true;
            },
            focus: function () {
                // prevent value inserted on focus
                return false;
            },
            select: function (event, ui) {
                var terms = split(this.value);
                // remove the current input
                terms.pop();
                // add the selected item
                terms.push(ui.item.value);
                // add placeholder to get the comma-and-space at the end
                terms.push("");
                this.value = terms.join(", ");
                return false;
            }
        });
});
