$(document).ready(function() {

    // Button with btn-js-click should be executed through JS
    $('button.btn-js-click').click(function() {
        if ($(this).data('url') === '') {
            alert('Attribute data-url not set.');
            return false;
        }
        window.location = $(this).data('url');
    });

    // Execute when form for Feeds is displayed
    if ($('form.form_solr_configuration').length > 0) {

        var form = $('form.form_solr_configuration'),
            formContainer = form.parent()

        formContainer.on('change', '.auto-submit, #solr_configuration_type_indexables input[value="indexer.article"]', function() {

            // Disabled buttons so use can't submit
            formContainer.find('button').attr('disabled', 'disabled');

            $.post(window.location, $('form.form_solr_configuration').serialize(), function(data) {
                if (typeof data.html !== 'undefined' && data.html !== '') {
                    // Dynamically update form
                    $('form.form_solr_configuration').replaceWith($('<div/>').html(data.html).text());
                }
            });
        });
    }

    // View raw response button on status page
    if ($('#viewRaw').length > 0) {
        $('#viewRaw').click(function() {
            $('#rawBody').toggleClass('hidden');
        });
    }
});
