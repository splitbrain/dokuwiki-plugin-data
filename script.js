/**
 * Init datepicker for all date fields
 *
 * @author Adrian Lang <lang@cosmocode.de>
 */
addInitEvent(function () {
    if (typeof calendar === 'undefined') return;
    var datepickers = getElementsByClass('data_type_dt', document, 'label');
    for (var i = 0 ; i < datepickers.length ; ++i) {
        var pick = datepickers[i].lastChild;
        if (!pick.id) {
            pick.id = 'data_datepicker' + i;
        }
        calendar.set(pick.id);
    }
});

/**
 * Init autocompletion for all page alias fields
 *
 * @author Adrian Lang <lang@cosmocode.de>
 */
addInitEvent(function () {
    if (typeof AutoCompletion === 'undefined') return;

    function prepare_li(li, value) {
        li.innerHTML = '<a href="#">' + value[1] + ' (' + value[0] + ')</a>';
        li.id = 'data__' + value[0].replace(/\W/g, '_');
        li._value = value[0];
        return li;
    }

    var classes = {'data_type_page' : [false, /data_type_(\w+) data_type_page/],
                   'data_type_pages': [true, /data_type_(\w+)s data_type_pages/] };
    for (var c_class in classes) {
        var pickers = getElementsByClass(c_class, document, 'label');
        for (var i = 0 ; i < pickers.length ; ++i) {
            AutoCompletion(pickers[i].lastChild,
                           'data_page_' + pickers[i].className.match(classes[c_class][1])[1],
                           classes[c_class][0],
                           prepare_li);
        }
    }
});
