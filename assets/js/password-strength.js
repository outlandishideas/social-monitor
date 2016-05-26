var password = document.getElementById('password');
var progress = document.getElementById('password-strength');
var meter = progress.firstElementChild;
var progressStyles = { 0: 'alert', 1: 'alert', 2: 'secondary', 3: 'secondary', 4: 'success' };

password.addEventListener('input', function () {
    var passwordValue = password.value;
    if (passwordValue){
        var calculatedPasswordStrength = zxcvbn(passwordValue).score;
        var strengthBarWidth = Math.max(1, calculatedPasswordStrength * 25);

        meter.style.width = [strengthBarWidth, '%'].join('');
        progress.className = ['progress', progressStyles[calculatedPasswordStrength]].join(' ');
    }
});
