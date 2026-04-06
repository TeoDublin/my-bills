document.addEventListener('DOMContentLoaded', function () {

    const authStage = document.querySelector('[data-auth-stage]');

    if (!authStage) {

        return;
    }

    const syncState = (showForgot) => {

        authStage.classList.toggle('is-forgot', showForgot);
    };

    document.querySelectorAll('[data-auth-view-toggle]').forEach((button) => {

        button.addEventListener('click', function () {

            syncState(button.dataset.authViewToggle === 'forgot');
        });
    });
});

