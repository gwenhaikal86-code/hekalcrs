document.addEventListener('DOMContentLoaded', function () {
  const faqButtons = document.querySelectorAll('.accordion-button');

  faqButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const isExpanded = button.getAttribute('aria-expanded') === 'true';
      faqButtons.forEach((btn) => {
        btn.classList.remove('show');
        btn.setAttribute('aria-expanded', 'false');
      });

      if (!isExpanded) {
        button.setAttribute('aria-expanded', 'true');
      }
    });
  });
});
