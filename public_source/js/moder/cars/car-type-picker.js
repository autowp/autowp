define(
    ['jquery', 'bootstrap', 'typeahead', 'bootstrap-tagsinput'],
    function($) {
        return {
            init: function(element) {
                var $select = $(element);
                var dataset = [];
                var selected = [];
                $select.find('option').each(function() {
                    var item = {
                        value: $(this).val(),
                        name: $(this).text()
                    };
                    dataset.push(item);
                    if ($(this).is(':selected')) {
                        selected.push(item);
                    }
                });
                
                $select.tagsinput({
                    itemValue: 'value',
                    itemText: 'name',
                    typeaheadjs: [{
                        minLength: 0
                    }, {
                        displayKey: 'name',
                        source: function(query, syncResults, asyncResults) {
                            if (query.length) {
                                var result = [];
                                var lcQuery = query.toLowerCase();
                                $.map(dataset, function(item) {
                                    var name = item.name.replace(/^[.]+/g, '').toLowerCase();
                                    if (name.indexOf(lcQuery) !== -1) {
                                        result.push(item);
                                    }
                                });
                                syncResults(result);
                            } else {
                                syncResults(dataset);
                            }
                        },
                        limit: 40
                    }]
                });
                
                $.map(selected, function(item) {
                    $select.tagsinput('add', item);
                });
            }
        }
    }
);