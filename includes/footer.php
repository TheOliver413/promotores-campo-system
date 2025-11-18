</main>

<footer>
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h5><i class="bi bi-briefcase-fill me-2"></i>Field Sales Manager</h5>
                <p>Sistema integral de gestión de promotores de campo, diseñado para optimizar operaciones y maximizar resultados.</p>
                <div class="social-links">
                    <a href="#" title="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="#" title="Twitter"><i class="bi bi-twitter"></i></a>
                    <a href="#" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                    <a href="#" title="Instagram"><i class="bi bi-instagram"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h5>Accesos Rápidos</h5>
                <ul>
                    <li><a href="/promotores-campo-system/admin/dashboard.php"><i class="bi bi-chevron-right"></i> Dashboard</a></li>
                    <li><a href="/promotores-campo-system/admin/proyectos.php"><i class="bi bi-chevron-right"></i> Proyectos</a></li>
                    <li><a href="/promotores-campo-system/admin/usuarios.php"><i class="bi bi-chevron-right"></i> Usuarios</a></li>
                    <li><a href="/promotores-campo-system/admin/reportes.php"><i class="bi bi-chevron-right"></i> Reportes</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h5>Soporte</h5>
                <ul>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Centro de Ayuda</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Documentación</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Contacto</a></li>
                    <li><a href="#"><i class="bi bi-chevron-right"></i> Términos y Condiciones</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h5>Contacto</h5>
                <ul>
                    <li><i class="bi bi-envelope me-2"></i> soporte@fieldsales.com</li>
                    <li><i class="bi bi-telephone me-2"></i> +57 (310) 3738600</li>
                    <li><i class="bi bi-geo-alt me-2"></i> Bogotá, Colombia</li>
                    <li><i class="bi bi-clock me-2"></i> Lun - Vie: 9:00 - 18:00</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Field Sales Manager. Todos los derechos reservados. | Versión 1.0.0</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Agregar funciones de utilidad -->
<script>
    // Funciones globales para usar en todo el sistema
    function showLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    function hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer') || createToastContainer();

        const toastId = 'toast_' + Date.now();
        const bgColor = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-info');

        const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgColor} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            delay: 3000
        });
        toast.show();

        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '11';
        document.body.appendChild(container);
        return container;
    }

    function getCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocalización no soportada por tu navegador'));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        lat: position.coords.latitude,
                        lon: position.coords.longitude
                    });
                },
                (error) => {
                    let message = 'Error al obtener ubicación';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            message = 'Debes permitir el acceso a tu ubicación';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = 'Información de ubicación no disponible';
                            break;
                        case error.TIMEOUT:
                            message = 'Tiempo de espera agotado al obtener ubicación';
                            break;
                    }
                    reject(new Error(message));
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });
    }
</script>

</body>

</html>