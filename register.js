document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registerForm");

    form.addEventListener("submit", async function (e) {
        e.preventDefault();
        clearErrors();

        let role = getVal("role");
        let fname = getVal("fname");
        let lname = getVal("lname");
        let username = getVal("username");
        let email = getVal("email");
        let password = getVal("password");
        let confirmPassword = getVal("confirm_password");
        let terms = document.getElementById("terms").checked;

        let hasError = false;

        // VALIDATION
        if (role === "") hasError = setError("role", "Please select a role.");
        if (fname === "") hasError = setError("fname", "First Name is required.");
        if (lname === "") hasError = setError("lname", "Last Name is required.");
        if (username === "") hasError = setError("username", "Username is required.");
        
        if (email === "") hasError = setError("email", "Email is required.");
        else if (!validateEmail(email)) hasError = setError("email", "Enter a valid email.");

        if (password === "") hasError = setError("password", "Password is required.");

        if (confirmPassword === "")
            hasError = setError("confirm_password", "Confirm your password.");
        else if (password !== confirmPassword)
            hasError = setError("confirm_password", "Passwords do not match.");

        if (!terms) hasError = setError("terms", "You must accept the Terms & Conditions.");

        if (hasError) return;

        // PREPARE FORM DATA
        const formData = new FormData(form);

        try {
            // SEND DATA TO signup.php
            let response = await fetch("signup.php", {
                method: "POST",
                body: formData
            });

            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            let result = await response.json();

            if (result.success) {
                // SUCCESS POPUP
                Swal.fire({
                    title: "Registration Successful!",
                    text: "You will be redirected to login page.",
                    icon: "success",
                    timer: 2500,
                    showConfirmButton: false
                });

                setTimeout(() => {
                    window.location.href = "login.html";
                }, 2600);

            } else {
                // ERROR POPUP
                Swal.fire({
                    title: "Registration Failed",
                    text: result.message || "An error occurred during registration.",
                    icon: "error"
                });
            }

        } catch (error) {
            console.error("Registration error:", error);
            Swal.fire({
                title: "Server Error",
                text: error.message || "Unable to connect to the server. Please check your internet connection and try again.",
                icon: "error"
            });
        }
    });
});

// HELPER FUNCTIONS

function validateEmail(email) {
    let re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function getVal(id) {
    return document.getElementById(id).value.trim();
}

function setError(id, message) {
    document.getElementById(id + "_error").textContent = message;
    document.getElementById(id).classList.add("error-border");
    return true; // mark error
}

function clearErrors() {
    document.querySelectorAll(".error").forEach(el => el.textContent = "");
    document.querySelectorAll(".error-border").forEach(el => el.classList.remove("error-border"));
}
