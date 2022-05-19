jQuery(document).ready(function($) {


    $(document).on('click ', '.add_line', function(e) {

        e.preventDefault();

        var $parent = $('#costs_main_new');

        var $div = $parent.find('.costs_main').last();

        var cost = $div.find('.wordpress_attributes');

        var theValue = $div.find('.wordpress_attributes option:selected').val();

        var cost2 = $div.find('.mailercloud_attributes');

        var theValue2 = $div.find('.mailercloud_attributes option:selected').val();

        if (cost && theValue && cost2 && theValue2) {

            var $clone = $div.clone();

            if ('email' != theValue2) {

                $clone.find('.mailercloud_attributes option[value=' + theValue2 + ']');

            }

            if ('user_email' != theValue) {

                $clone.find('.wordpress_attributes option[value=' + theValue + ']');

            }

            $clone.find('.wordpress_attributes').val('');

            $clone.find('.wordpress_attributes').removeClass('field_error');

            $clone.find('.wordpress_attributes').find(":selected").prop("selected", false);

            $clone.find('.mailercloud_attributes').val('');

            $clone.find('.mailercloud_attributes').removeClass('field_error');

            $clone.find('.mailercloud_attributes').find(":selected").prop("selected", false);

            $clone.addClass('last_row');

            $div.after($clone);

            $div.find('.add_line').remove();

            $div.removeClass('last_row');

            $div.find('.action_btns').append('<button type="button" class="del_line">-</button>');

            cost.removeClass('field_error');

            cost2.removeClass('field_error');

        } else {

            cost.addClass('field_error');

            cost2.addClass('field_error');

        }

    });





    $(document).on('change ', '.wordpress_attributes', function(e) {

        var selected_val = $(this).find(":selected").val();

        var selected = $(this).find(":selected");

        var $parent = $('#costs_main_new');

        var $divs = $(this).parents('.costs_main').siblings('.costs_main');

        $divs.each(function(i, element) {

            console.log('element', element);

            var $div = $(element);

            var cost = $div.find('.wordpress_attributes');

            var theValue = $div.find('.wordpress_attributes option:selected').val();

            if (theValue == selected_val) {

                e.preventDefault();

                Swal.fire({

                    title: 'Error!',

                    text: 'This attribute is already used',

                    icon: 'error',

                    showCancelButton: true,

                });

                $(this).val('');

                selected.prop("selected", false);

                return;

            }

        });


    });



    $(document).on('change ', '.mailercloud_attributes', function(e) {
        e.preventDefault();
        var selected_val = $(this).find(":selected").val();

        var selected = $(this).find(":selected");

        var $parent = $('#costs_main_new');

        var $divs = $(this).parents('.costs_main').siblings('.costs_main');

        $divs.each(function(i, element) {

            var $div = $(element);

            var cost2 = $div.find('.mailercloud_attributes');

            var theValue2 = $div.find('.mailercloud_attributes option:selected').val();

            if (theValue2 == selected_val) {

                e.preventDefault();

                Swal.fire({

                    title: 'Error!',

                    text: 'This attribute is already used',

                    icon: 'error',

                    showCancelButton: true,

                });

                $(this).val('');

                selected.prop("selected", false)

                return;
            }

        });

    });



    $(document).on('change ', '.mailercloud_attributes_custom', function(e) {
        e.preventDefault();
        var selected_val = $(this).find(":selected").val();

        var selected = $(this).find(":selected");

        var $divs = $(this).parents('.costs_main_custom .repeat_div').siblings();

        $divs.each(function(i, element) {

            var $div = $(element);

            var theValue2 = $div.find('.mailercloud_attributes_custom option:selected').val();
            if (theValue2 == selected_val) {

                e.preventDefault();

                Swal.fire({

                    title: 'Error!',

                    text: 'This attribute is already used',

                    icon: 'error',

                    showCancelButton: true,

                });

                $(this).val('');

                selected.prop("selected", false)

                return;
            }

        });

    });



    $(document).on('click ', '.del_line', function(e) {
        e.preventDefault();

        var $parent = $('#costs_main_new');
        if ($(this).parents('.costs_main .repeat_div').hasClass('last_row')) {} else {

            var $div = $(this).parents('.costs_main .repeat_div');

            var cost = $div.find('.wordpress_attributes');

            var theValue = $div.find('.wordpress_attributes option:selected').val();

            var cost2 = $div.find('.mailercloud_attributes');

            var theValue2 = $div.find('.mailercloud_attributes option:selected').val();
            if (cost && theValue && cost2 && theValue2) {

                var $clone = $parent.find('.costs_main .repeat_div');

                if ('email' != theValue2) {

                    $clone.find('.mailercloud_attributes option[value=' + theValue2 + ']');

                }

                if ('user_email' != theValue) {

                    $clone.find('.wordpress_attributes option[value=' + theValue + ']');

                }

            }

            var $div = $(this).parents('.costs_main .repeat_div').remove();

        }





    });



    /**

     * add custom attributes 

     * 

     * 

     * */

    $(document).on('click ', '.add_line_custom', function(e) {
        e.preventDefault();

        var $parent = $('#costs_main_new');

        var $div = $parent.find('.costs_main_custom .repeat_div').last();

        var cost = $div.find('.wordpress_attributes');

        var theValue = $div.find('.wordpress_attributes option:selected').val();

        var cost2 = $div.find('.mailercloud_attributes_custom');

        var theValue2 = $div.find('.mailercloud_attributes_custom  option:selected').val();

        if (theValue && theValue2) {

            var $clone = $div.clone();

            if ('email' != theValue2) {

                $clone.find('.mailercloud_attributes_custom option[value=' + theValue2 + ']');

            }

            if ('user_email' != theValue) {

                $clone.find('.wordpress_attributes option[value=' + theValue + ']');

            }

            $clone.find('.wordpress_attributes').val('');

            $clone.find('.wordpress_attributes').removeClass('field_error');

            $clone.addClass('last_row');

            $clone.find('.mailercloud_attributes_custom').val('');

            $clone.find('.mailercloud_attributes_custom').removeClass('field_error');

            $div.after($clone);

            $div.find('.add_line_custom').remove();

            $div.removeClass('last_row');

            $div.find('.action_btns').append('<button type="button" class="del_line_custom">-</button>');

            cost.removeClass('field_error');

            cost2.removeClass('field_error');

        } else {

            cost.addClass('field_error');

            cost2.addClass('field_error');

        }

    });

    $(document).on('click ', '.del_line_custom', function(e) {

        e.preventDefault();

        var $parent = $('#costs_main_new');
        if ($(this).parents('.costs_main_custom .repeat_div').hasClass('last_row')) {} else {

            var $div = $(this).parents('.costs_main_custom .repeat_div');

            var theValue = $div.find('.wordpress_attributes option:selected').val();

            var theValue2 = $div.find('.mailercloud_attributes_custom  option:selected').val();

            if (theValue && theValue2) {

                var $clone = $parent.find('.costs_main_custom .repeat_div');

                if ('email' != theValue) {

                    $clone.find('.mailercloud_attributes option[value=' + theValue + ']');

                }

                if ('user_email' != theValue2) {

                    $clone.find('.mailercloud_attributes_custom option[value=' + theValue2 + ']');

                }

            }

            var $div = $(this).parents('.costs_main_custom .repeat_div').remove();

        }





    });



    $(document).on('change ', '#list_id', function(e) {

        e.preventDefault();

        var list_name = $("#list_id option:selected").text();

        if (list_name != '') {

            $("#selected_list_name").val(list_name);

        }



    });

    $(document).on('click ', '#new_property', function(e) {

        e.preventDefault();

        $("#myModal").css('display', 'block');

    });



    $(document).on('click ', 'span.close', function(e) {

        e.preventDefault();

        $("#myModal").css('display', 'none');

    });



    $(document).on('submit', 'form#newPropertyForm', function(e) {

        e.preventDefault();

        // Get form

        var cartForm = $(this);

        var form = $('#newPropertyForm')[0];

        // Create an FormData object

        var fd = new FormData(form);

        fd.append('action', 'mailercloud_create_new_property');

        $.ajax({

            type: 'POST',

            url: admAjax.ajaxurl,

            data: fd,

            async: true,

            cache: false,

            contentType: false,

            dataType: 'JSON',

            processData: false,

            beforeSend: function() {

                $('.loader_mailercloud').show();

            },

            success: function(response) {
                if (response.id) {
                    var find = "custom_fields_";
                    var key_id = find + response.id;

                    $('.mailercloud_attributes')

                    .append($("<option></option>")

                        .attr("value", key_id)

                        .text(response.name));
                    $('#response_feedback')

                    .removeClass('error')

                    .addClass('success')

                    .html("<p><b>" + response.message + "</b></p>");

                    setTimeout(function() {

                        $("#myModal").css('display', 'none');

                    }, 2000);

                } else {

                    $.each(response.errors, function(key, value) {

                        $('#response_feedback')

                        .addClass('error')

                        .html("<p><b>" + value.field + '</b>:  ' + value.message + "</p>");
                    });

                }

            },

            complete: function(xhr, textStatus) {

                console.log(xhr.status);

            }

        }).then(function(data) {
            $('.loader_mailercloud').hide();
        });



    });





    $(document).on('submit', 'form#contact_sync_now_form', function(e) {

        e.preventDefault();

        // Get form

        var cartForm = $(this);
        var form = $('#contact_sync_now_form')[0];

        // Create an FormData object

        var fd = new FormData(form);

        fd.append('action', 'mailercloud_sync_contacts_now_ajax');
        var request = $.ajax({

            type: 'POST',

            url: admAjax.ajaxurl,

            data: fd,

            async: true,

            cache: false,

            contentType: false,

            dataType: 'JSON',

            processData: false,

            beforeSend: function() {

                $('#sync_feedback')

                .removeClass('error')

                .addClass('success')

                .html('<p><b>' + 'Contact synchronization has been started' + '</b></p>');

                $('.loader_mailercloud').show();

            },

            success: function(response) {

                if (response) {
                    console.log('response', response);

                    if (response.errors) {

                        $.each(response.errors, function(key, value) {

                            $('#sync_feedback')

                            .addClass('error')

                            .html("<p><b>" + value.field + '</b>:  ' + value.message + "</p>");
                        });

                    } else if (response.data) {

                        var textmsg = '';

                        textmsg += '<p><b>' + response.message + '</b></p>';

                        textmsg += '<p><span> Total Contacts Inserted  </span>' + response.data['inserted'] + '</p>';

                        textmsg += '<p><span>Total Contacts Skipped  </span>' + response.data['skipped'] + '</p>';

                        textmsg += '<p><span>Total Contacts Submitted  </span>' + response.data['submitted'] + '</p>';

                        textmsg += '<p><span>Total Updated Contacts  </span>' + response.data['updated'] + '</p>';

                        $('#sync_feedback')

                        .removeClass('error')

                        .addClass('success')

                        .html(textmsg);

                    } else if (!response.status) {
                        $('#sync_feedback')

                        .addClass('error')

                        .html("<p><b>" + response.message + "</b></p>");
                    } else {

                        $.each(response.errors, function(key, value) {

                            $('#sync_feedback')

                            .addClass('error')

                            .html("<p><b>" + value.field + '</b>:  ' + value.message + "</p>");

                        });

                    }

                }

            },

            complete: function(xhr, textStatus) {

                console.log(xhr.status);

            }

        }).then(function(data) {

            $('.loader_mailercloud').hide();

        });



    });







});