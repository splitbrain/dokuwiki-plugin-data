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
