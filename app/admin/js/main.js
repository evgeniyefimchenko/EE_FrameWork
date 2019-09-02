actions = {
    showNotification: function (text_message, color, from, align) {
        // color = 'info'; // 'primary', 'info', 'success', 'warning', 'danger'
        return $.notify({
            icon: "nc-icon nc-app",
            message: text_message
        }, {
            type: color,
            timer: 5000,
            placement: {
                from: from,
                align: align
            }
        });
    }
}

$(document).ready(function () {
    $('[data-toggle="tooltip"]').tooltip({
            trigger : 'hover'
        });
		
    if ($("[data-toggle='switch']").length != 0) {
        $("[data-toggle='switch']").bootstrapSwitch();
    }
});