// Global App Functions

// Import Bootstrap
const bootstrap = window.bootstrap

// Show loading spinner
function showLoading() {
    const overlay = document.getElementById("loadingOverlay")
    if (overlay) {
        overlay.classList.add("active")
    }
}

// Hide loading spinner
function hideLoading() {
    const overlay = document.getElementById("loadingOverlay")
    if (overlay) {
        overlay.classList.remove("active")
    }
}

// Show toast notification
function showToast(message, type = "success") {
    const toastContainer = document.getElementById("toastContainer")
    if (!toastContainer) return

    const toastId = "toast-" + Date.now()
    const bgClass = type === "success" ? "bg-success" : "bg-danger"

    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `

    toastContainer.insertAdjacentHTML("beforeend", toastHTML)

    const toastElement = document.getElementById(toastId)
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 })
    toast.show()

    toastElement.addEventListener("hidden.bs.toast", () => {
        toastElement.remove()
    })
}

// Confirm delete action
function confirmDelete(message = "¿Está seguro de eliminar este registro?") {
    return confirm(message)
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString)
    return date.toLocaleDateString("es-ES", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    })
}

// Format datetime
function formatDateTime(dateString) {
    const date = new Date(dateString)
    return date.toLocaleString("es-ES", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    })
}

// AJAX helper function
async function fetchAPI(url, options = {}) {
    try {
        showLoading()
        const response = await fetch(url, {
            ...options,
            headers: {
                "Content-Type": "application/json",
                ...options.headers,
            },
        })

        const data = await response.json()
        hideLoading()

        if (!response.ok) {
            throw new Error(data.message || "Error en la solicitud")
        }

        return data
    } catch (error) {
        hideLoading()
        console.error("[v0] API Error:", error)
        showToast(error.message, "error")
        throw error
    }
}

// Initialize tooltips
document.addEventListener("DOMContentLoaded", () => {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))
})

// Auto-hide alerts after 5 seconds
document.addEventListener("DOMContentLoaded", () => {
    const alerts = document.querySelectorAll(".alert:not(.alert-permanent)")
    alerts.forEach((alert) => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert)
            bsAlert.close()
        }, 5000)
    })
})
