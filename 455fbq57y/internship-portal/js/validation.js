document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("form[data-validate]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      const password = form.querySelector('input[name="password"]');
      const confirm = form.querySelector('input[data-match="password"]');
      if (password && confirm && password.value !== confirm.value) {
        event.preventDefault();
        confirm.setCustomValidity("Passwords do not match.");
        confirm.reportValidity();
      } else if (confirm) {
        confirm.setCustomValidity("");
      }
    });
    const confirm = form.querySelector('input[data-match="password"]');
    if (confirm) confirm.addEventListener("input", () => confirm.setCustomValidity(""));
  });

  document.querySelectorAll('input[type="file"]').forEach((input) => {
    input.addEventListener("change", () => {
      const file = input.files && input.files[0];
      if (file && file.size > 3 * 1024 * 1024) {
        alert("Please choose a file smaller than 3 MB.");
        input.value = "";
      }
    });
  });
});

function toggleMenu() {
  const nav = document.getElementById("navLinks");
  if (nav) nav.classList.toggle("nav-open");
}
