/**
 * Common AJAX request for all instalment operations.
 */
function nnInstalmentAjaxRequest(action, data, successCallback) {

    $.ajax({
        url: 'adminHandler.php'
            + '?plugin=xt_novalnet_config'
            + '&load_section=xt_novalnet_operations'
            + '&pg=' + encodeURIComponent(action),

        type: 'POST',

        data: data,

        dataType: 'json',

        success: function (response) {

            if (typeof successCallback === 'function') {
                successCallback(response);
            }

        },

        error: function (xhr, status, error) {

            console.error(
                'Novalnet instalment AJAX error:',
                error
            );

            alert(
                'Unable to process the instalment operation. Please try again.'
            );
        }
    });
}


/**
 * Refund an individual instalment.
 */
function nnInstalmentRefund(orderId, cycle) {

    var refundkey = orderId + '_' + (cycle - 1);
    var amount = $('#nn_refund_trans_amount_' + refundkey).val();
    var tid = $('#nn_refund_tid_' + refundkey).val();
    if (!confirm(
        'Are you sure you want to refund this instalment?'
    )) {
        return false;
    }

    nnInstalmentAjaxRequest(
        'instalmentRefundProcess',
        {
            orders_id: orderId,
            tid: tid,
            instalment_cycle_no: cycle,
            novalnet_refund_amount: amount
        },
        function (response) {

            if (response.message) {
                alert(response.message);
            }

            if (response.success) {
                contentTabs.getActiveTab().getUpdater().refresh();
            }
        }
    );

    return false;
}

function nnInstalmentRefundCancel(refundKey) {
    var row = document.getElementById(
        'nn_instalment_refund_' + refundKey
    );

    if (!row) {
        return;
    }

    row.style.display = 'none';
}


function nnInstalmentcancelRemaining(orderId) {
    if (confirm('Do you want to cancel Remaining cycles')) {
        nnInstalmentAjaxRequest(
            'instalmentcancelcycles',
            {
                orders_id: orderId,
                cancel_type: 'remaining_cycles'

            },
            function (response) {

                if (response.message) {
                    alert(response.message);
                }

                if (response.success) {
                    contentTabs.getActiveTab().getUpdater().refresh();
                }
            }
        );
    }
}

function nnInstalmentcancelAll(orderId) {
    if (confirm('Do you want to cancel All cycles')) {
        nnInstalmentAjaxRequest(
            'instalmentcancelcycles',
            {
                orders_id: orderId,
                cancel_type: 'all_cycles'

            },
            function (response) {
                if (response.message) {
                    alert(response.message);
                }

                if (response.success) {
                    contentTabs.getActiveTab().getUpdater().refresh();
                }
            }
        );
    }
}