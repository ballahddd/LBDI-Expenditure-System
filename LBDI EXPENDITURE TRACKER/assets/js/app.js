(() => {
  const form = document.getElementById("loginForm");
  const identifier = document.getElementById("email");
  const password = document.getElementById("password");
  const togglePassword = document.getElementById("togglePassword");
  const submitBtn = document.getElementById("submitBtn");
  const formError = document.getElementById("formError");
  const formSuccess = document.getElementById("formSuccess");

  if (!form || !identifier || !password || !togglePassword || !submitBtn || !formError || !formSuccess) return;

  function setBusy(isBusy) {
    submitBtn.disabled = isBusy;
    submitBtn.setAttribute("aria-busy", String(isBusy));
  }

  function showError(message) {
    formSuccess.textContent = "";
    formSuccess.hidden = true;
    formError.textContent = message;
    formError.hidden = false;
  }

  function showSuccess(message) {
    formError.textContent = "";
    formError.hidden = true;
    formSuccess.textContent = message;
    formSuccess.hidden = false;
  }

  function clearError() {
    formError.textContent = "";
    formError.hidden = true;
    formSuccess.textContent = "";
    formSuccess.hidden = true;
  }

  function setInvalid(el, isInvalid) {
    el.setAttribute("aria-invalid", isInvalid ? "true" : "false");
  }

  togglePassword.addEventListener("click", () => {
    const isHidden = password.type === "password";
    password.type = isHidden ? "text" : "password";
    togglePassword.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
  });

  form.addEventListener("input", () => {
    clearError();
    setInvalid(identifier, false);
    setInvalid(password, false);
  });

  form.addEventListener("submit", (e) => {
    clearError();

    const idVal = String(identifier.value || "").trim();
    const pwVal = String(password.value || "");

    let ok = true;
    if (idVal.length < 3) {
      ok = false;
      setInvalid(identifier, true);
    }
    if (pwVal.length < 6) {
      ok = false;
      setInvalid(password, true);
    }

    if (!ok) {
      e.preventDefault();
      showError("Please enter your email/username and password.");
      return;
    }

    // UX only: real auth happens in login.php later.
    setBusy(true);
    window.setTimeout(() => setBusy(false), 1200);
  });

  const params = new URLSearchParams(window.location.search);
  const status = params.get("status");
  const message = params.get("message");
  if (message) {
    if (status === "success") {
      showSuccess(message);
    } else {
      showError(message);
    }
  }
})();
