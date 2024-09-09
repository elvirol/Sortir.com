
    document.addEventListener('DOMContentLoaded', () => {
    const navToggle = document.getElementById('nav-toggle');
    const navContent = document.getElementById('nav-content-mobile');

    navToggle.addEventListener('click', () => {
    if (navContent.classList.contains('hidden')) {
    navContent.classList.remove('hidden');
    navContent.style.maxHeight = navContent.scrollHeight + 'px';
} else {
    navContent.style.maxHeight = '0';
    setTimeout(() => navContent.classList.add('hidden'), 300); // Hide after animation
}
});
});
