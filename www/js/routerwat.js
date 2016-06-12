$(document).ready(function() {
    if (jsData.time_until_update_allowed != 0) {
        var startTime = new Date().getTime();

        var countDownInterval = setInterval(function() {
            var timeLeft = Math.ceil(jsData.time_until_update_allowed - (new Date().getTime() - startTime)/1000);
            if (timeLeft <= 0) {
                clearInterval(countDownInterval);
                $("#horses-alert").addClass("hidden");
                $("#wait-time-warning").addClass("hidden");
                $("#form-force-update").removeClass("hidden");
            } else {
                $(".wait-time-left").text(timeLeft);
            }
        },1000);
    }
});