document.getElementById("loginForm").addEventListener("submit", async function(e) {
    e.preventDefault();

    let username = document.getElementById("email").value.trim();
    let password = document.getElementById("password").value.trim();

    // Clear previous errors
    let errors = [];
    if (username === "") errors.push("Email/Username is required.");
    if (password === "") errors.push("Password is required.");

    if (errors.length > 0) {
        Swal.fire({
            icon: "error",
            title: "Validation Error",
            html: errors.join("<br>")
        });
        return;
    }

    // Send login request
    let formData = new FormData();
    formData.append("username", username);
    formData.append("password", password);

    try {
        let response = await fetch("login.php", {
            method: "POST",
            body: formData
        });

        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        let result = await response.json();

        if (result.success) {
            // Log role for debugging
            console.log("Login successful. Role received:", result.role);
            
            Swal.fire({
                icon: "success",
                title: "Login Successful",
                text: "Welcome " + result.username,
                timer: 1500,
                showConfirmButton: false
            });

            setTimeout(() => {
                // Redirect based on role - be explicit with each check
                let redirectUrl = null;
                const userRole = result.role ? result.role.trim().toLowerCase() : '';
                
                console.log("Redirecting user with role:", userRole);
                
                // Check role and set redirect URL
                if (userRole === "student") {
                    redirectUrl = "studentDashboard.php";
                } else if (userRole === "faculty") {
                    redirectUrl = "facultyDashboard.php";
                } else if (userRole === "faculty_intern") {
                    redirectUrl = "facultyInternDashboard.php";
                } else {
                    // Fallback: use index.php which handles server-side routing
                    console.warn("Unknown role:", userRole, "- redirecting to index.php");
                    redirectUrl = "index.php";
                }
                
                console.log("Redirecting to:", redirectUrl);
                // Redirect to the appropriate dashboard
                window.location.href = redirectUrl;
            }, 1500);

        } else {
            Swal.fire({
                icon: "error",
                title: "Login Failed",
                text: result.message || "Invalid username or password."
            });
        }

    } catch (err) {
        console.error("Login error:", err);
        Swal.fire({
            icon: "error",
            title: "Server Error",
            text: err.message || "Unable to connect to the server. Please check your internet connection and try again."
        });
    }
});
