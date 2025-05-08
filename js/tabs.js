document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.nav-tab');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            contents.forEach(c => c.classList.remove('active'));

            tab.classList.add('nav-tab-active');
            document.querySelector(tab.getAttribute('href')).classList.add('active');
        });
    });

    // Activate the first tab by default
    tabs[0].click();
});