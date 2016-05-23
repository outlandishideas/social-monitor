var password = document.getElementById('password');
var passwordStrengthBar = document.getElementById('password-strength');

password.addEventListener('input', function () {
    var passwordValue = password.value;
    if(passwordValue){
        var calculatedPasswordStrength = zxcvbn(passwordValue).score;
        passwordStrengthBar.setAttribute('aria-valuenow', calculatedPasswordStrength);
    }

});
