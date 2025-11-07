// Geolocation Functions

let currentPosition = null

// Get current position
function getCurrentPosition() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error("Geolocalización no soportada por el navegador"))
            return
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                currentPosition = {
                    lat: position.coords.latitude,
                    lon: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                }
                console.log("[v0] Position obtained:", currentPosition)
                resolve(currentPosition)
            },
            (error) => {
                console.error("[v0] Geolocation error:", error)
                let errorMessage = "Error al obtener ubicación"

                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage = "Permiso de ubicación denegado"
                        break
                    case error.POSITION_UNAVAILABLE:
                        errorMessage = "Ubicación no disponible"
                        break
                    case error.TIMEOUT:
                        errorMessage = "Tiempo de espera agotado"
                        break
                }

                reject(new Error(errorMessage))
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0,
            },
        )
    })
}

// Watch position (for real-time tracking)
let watchId = null

function startWatchingPosition(callback) {
    if (!navigator.geolocation) {
        console.error("[v0] Geolocation not supported")
        return
    }

    watchId = navigator.geolocation.watchPosition(
        (position) => {
            currentPosition = {
                lat: position.coords.latitude,
                lon: position.coords.longitude,
                accuracy: position.coords.accuracy,
            }

            if (callback) {
                callback(currentPosition)
            }
        },
        (error) => {
            console.error("[v0] Watch position error:", error)
        },
        {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 0,
        },
    )
}

function stopWatchingPosition() {
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId)
        watchId = null
    }
}

// Calculate distance between two points (Haversine formula)
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371 // Earth radius in km
    const dLat = ((lat2 - lat1) * Math.PI) / 180
    const dLon = ((lon2 - lon1) * Math.PI) / 180

    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.sin(dLon / 2) * Math.sin(dLon / 2)

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
    const distance = R * c

    return distance // in km
}

// Check if point is within geofence (simple circle check)
function isWithinGeofence(pointLat, pointLon, centerLat, centerLon, radiusKm) {
    const distance = calculateDistance(pointLat, pointLon, centerLat, centerLon)
    return distance <= radiusKm
}
