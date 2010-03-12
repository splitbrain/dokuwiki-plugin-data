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

    var classes = { 'data_type_page': false, 'data_type_pages': true };
    for (var c_class in classes) {
        var pickers = getElementsByClass(c_class, document, 'label');
        for (var i = 0 ; i < pickers.length ; ++i) {
            AutoCompletion(pickers[i].lastChild, 'data_page_' + pickers[i].className.match(/data_type_(\w+) data_type_pages?/)[1], classes[c_class], prepare_li);
        }
    }
});
