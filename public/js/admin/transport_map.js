const simulateRoutePattern = '/gestiontransport/transport/:id/simulate'; // Match Symfony route

document.addEventListener('DOMContentLoaded', () => {
    // Tab Switching Logic (unchanged)
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    if (tabButtons.length === 0 || tabContents.length === 0) {
        console.error('Tab buttons or contents not found in DOM');
        return;
    }

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            console.log(`Switching to tab: ${button.getAttribute('data-tab')}`);
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            button.classList.add('active');
            const tabId = button.getAttribute('data-tab');
            const targetContent = document.getElementById(tabId);
            if (targetContent) {
                targetContent.classList.add('active');
                console.log(`Activated tab content: ${tabId}`);
            } else {
                console.error(`Tab content with ID ${tabId} not found`);
            }

            if (tabId === 'transports' && window.map) {
                setTimeout(() => {
                    console.log('Invalidating map size');
                    window.map.invalidateSize();
                }, 100);
            }
        });
    });

    // Initialize Map
    let map;
    try {
        map = L.map('map', {
            minZoom: 2,
            maxZoom: 18
        }).setView([36.8065, 10.1815], 8); // Default to Tunis coordinates
        window.map = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            tileSize: 512,
            zoomOffset: -1
        }).addTo(map);

        setTimeout(() => {
            console.log('Initial map size invalidation');
            map.invalidateSize();
        }, 500);
    } catch (error) {
        console.error('Map initialization failed:', error);
    }

    // Store markers and routing controls
    const transportMarkers = {};
    let currentRouteControl = null;
    const transportRoutes = {};

    // Configuration from Twig (assumed to be injected via window.transportConfigs)
    const cfgs = (window.transportConfigs || []).filter(cfg => cfg !== null && cfg !== undefined);

    console.log('Transport configurations:', cfgs);

    // Initialize Mercure connection
    let eventSource = null;
    let isMercureInitialized = false;

    // Show route and start simulation when clicking a transport row
    document.querySelectorAll('.transport-row').forEach(row => {
        row.addEventListener('click', async () => {
            const transportId = row.getAttribute('data-transport-id');
            const cfg = cfgs.find(c => c.transportId == transportId);

            if (!cfg) {
                showModal('Transport non trouvé ou non actif.');
                return;
            }

            // Clear previous route and markers
            if (currentRouteControl) {
                map.removeControl(currentRouteControl);
                currentRouteControl = null;
            }
            map.eachLayer(layer => {
                if (layer instanceof L.Marker) {
                    map.removeLayer(layer);
                }
            });

            // Validate coordinates
            const hasValidDepart = cfg.departLat != null && cfg.departLon != null &&
                                 !isNaN(parseFloat(cfg.departLat)) && !isNaN(parseFloat(cfg.departLon)) &&
                                 parseFloat(cfg.departLat) >= -90 && parseFloat(cfg.departLat) <= 90 &&
                                 parseFloat(cfg.departLon) >= -180 && parseFloat(cfg.departLon) <= 180;
            const hasValidArrivee = cfg.arriveeLat != null && cfg.arriveeLon != null &&
                                  !isNaN(parseFloat(cfg.arriveeLat)) && !isNaN(parseFloat(cfg.arriveeLon)) &&
                                  parseFloat(cfg.arriveeLat) >= -90 && parseFloat(cfg.arriveeLat) <= 90 &&
                                  parseFloat(cfg.arriveeLon) >= -180 && parseFloat(cfg.arriveeLon) <= 180;

            if (!hasValidDepart || !hasValidArrivee) {
                console.warn(`Invalid coordinates for transport #${transportId}`, cfg);
                showModal('Les coordonnées de ce trajet ne sont pas disponibles.');
                return;
            }

            try {
                const from = L.latLng(parseFloat(cfg.departLat), parseFloat(cfg.departLon));
                const to = L.latLng(parseFloat(cfg.arriveeLat), parseFloat(cfg.arriveeLon));

                // Add departure and destination markers
                L.marker(from, {
                    icon: L.divIcon({
                        html: '<i class="fas fa-map-marker-alt fa-2x" style="color:#FF0000"></i>',
                        className: 'custom-icon',
                        iconSize: [24, 24],
                        iconAnchor: [12, 24]
                    })
                }).addTo(map).bindPopup(`Départ: ${cfg.departAddress || 'N/A'}`);

                L.marker(to, {
                    icon: L.divIcon({
                        html: '<i class="fas fa-map-marker-alt fa-2x" style="color:#28A745"></i>',
                        className: 'custom-icon',
                        iconSize: [24, 24],
                        iconAnchor: [12, 24]
                    })
                }).addTo(map).bindPopup(`Destination: ${cfg.arriveeAddress || 'N/A'}`);

                // Add routing control
                currentRouteControl = L.Routing.control({
                    waypoints: [from, to],
                    router: L.Routing.osrmv1({
                        serviceUrl: 'https://router.project-osrm.org/route/v1',
                        profile: 'driving'
                    }),
                    show: false,
                    addWaypoints: false,
                    lineOptions: {
                        styles: [{ color: '#4285F4', opacity: 0.7, weight: 5 }]
                    },
                    createMarker: () => null,
                    itinerary: {
                        template: '<div class="hidden-instructions"></div>'
                    }
                }).addTo(map);

                currentRouteControl.on('routesfound', function(e) {
                    transportRoutes[transportId] = e.routes;
                    if (e.routes && e.routes[0]) {
                        const distance = (e.routes[0].summary.totalDistance / 1000).toFixed(1);
                        const duration = Math.round(e.routes[0].summary.totalTime / 60);
                        console.log(`Route for transport #${transportId}: ${distance} km, ${duration} min`);
                    }
                });

                // Fit map bounds
                const bounds = L.latLngBounds([from, to]);
                map.fitBounds(bounds, { padding: [50, 50], maxZoom: 12 });

                // Start simulation for this transport
                await startSimulation(transportId, cfg);
            } catch (error) {
                console.error(`Error displaying route for transport #${transportId}:`, error);
                showModal('Erreur lors de l’affichage du trajet.');
            }
        });
    });

    async function startSimulation(transportId, cfg) {
        try {
            showLoading();

            // Validate configuration
            if (!cfg || !cfg.transportId || cfg.transportId != transportId) {
                throw new Error('Configuration mismatch for transport #' + transportId);
            }

            // Initialize vehicle marker at departure point
            const latlng = [parseFloat(cfg.departLat), parseFloat(cfg.departLon)];
            if (!isNaN(latlng[0]) && !isNaN(latlng[1])) {
                transportMarkers[transportId] = L.marker(latlng, {
                    icon: L.divIcon({
                        html: '<i class="fas fa-truck fa-2x" style="color:#F5A623"></i>',
                        className: 'custom-icon',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    })
                }).addTo(map).bindPopup(`Transport #${transportId} en cours`);
            } else {
                console.warn(`Cannot initialize marker for transport #${transportId} due to invalid departure coordinates`);
                return;
            }

            // Trigger simulation for this transport
            const simulationUrl = simulateRoutePattern.replace(':id', transportId);
            console.log('Simulation URL:', simulationUrl);
            const response = await fetch(simulationUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                let errorMessage = 'Erreur lors du lancement de la simulation.';
                try {
                    const errorData = await response.json();
                    errorMessage = errorData.message || errorMessage;
                    console.error('Simulation error details:', errorData);
                } catch (e) {
                    console.error('Failed to parse error response:', e);
                    if (response.status === 404) {
                        errorMessage = `Transport #${transportId} introuvable. Vérifiez l'ID du transport.`;
                    } else if (response.status === 401) {
                        errorMessage = 'Authentification requise. Veuillez vous connecter.';
                        setTimeout(() => window.location.href = '/login', 2000);
                    }
                }
                throw new Error(errorMessage);
            }

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Échec de la simulation');
            }

            if (!isMercureInitialized) {
                await initializeMercureConnection([transportId]);
                isMercureInitialized = true;
            }
        } catch (error) {
            console.error(`Simulation error for transport #${transportId}:`, error);
            showModal(error.message || 'Erreur: Problème de communication avec le serveur');
            resetSimulationState(transportId);
        } finally {
            const spinner = document.getElementById('loadingSpinner');
            if (spinner) spinner.classList.add('hidden');
        }
    }

    function initializeMercureConnection(transportIds) {
        return new Promise((resolve, reject) => {
            try {
                if (eventSource) {
                    console.log('Closing existing Mercure connection');
                    eventSource.close();
                    eventSource = null;
                }

                const mercureUrl = new URL('http://localhost:3000/.well-known/mercure'); // Match working code
                transportIds.forEach(id => {
                    mercureUrl.searchParams.append('topic', `tracking/transport/${id}`);
                });

                console.log('Connecting to Mercure hub with URL:', mercureUrl.toString());

                eventSource = new EventSource(mercureUrl);

                eventSource.onopen = () => {
                    console.log('Mercure connection established for transports:', transportIds);
                    resolve();
                };

                eventSource.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        console.log('Received Mercure update:', data);

                        if (data.transportId && data.latitude && data.longitude) {
                            updateVehiclePosition(data.transportId, data.latitude, data.longitude);
                        }
                    } catch (error) {
                        console.error('Error processing Mercure update:', error);
                    }
                };

                eventSource.onerror = function(event) {
                    console.error('Mercure connection error:', event);
                    setTimeout(() => {
                        if (eventSource) {
                            console.log('Attempting to reconnect to Mercure...');
                            initializeMercureConnection(transportIds)
                                .then(() => console.log('Reconnected to Mercure successfully'))
                                .catch(err => console.error('Failed to reconnect to Mercure:', err));
                        }
                    }, 5000);
                };

                window.mercureEventSource = eventSource;
            } catch (error) {
                console.error('Error setting up Mercure:', error);
                reject(error);
            }
        });
    }

    function updateVehiclePosition(transportId, latitude, longitude) {
        const latlng = [parseFloat(latitude), parseFloat(longitude)];
        if (isNaN(latlng[0]) || isNaN(latlng[1])) {
            console.error('Invalid coordinates for transport #' + transportId + ':', latitude, longitude);
            return;
        }

        console.log(`Updating position for transport #${transportId} to:`, latlng);

        if (transportMarkers[transportId]) {
            transportMarkers[transportId].setLatLng(latlng);
        } else {
            transportMarkers[transportId] = L.marker(latlng, {
                icon: L.divIcon({
                    html: '<i class="fas fa-truck fa-2x" style="color:#F5A623"></i>',
                    className: 'custom-icon',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                })
            }).addTo(map).bindPopup(`Transport #${transportId} en cours`);
        }

        const cfg = cfgs.find(c => c.transportId == transportId);
        if (cfg) {
            const route = transportRoutes[transportId];
            if (route && route[0]) {
                const totalDistance = cfg.trajetEnKm || 1;
                const distanceCovered = calculateDistanceCovered(latlng, route[0]);
                const pct = ((distanceCovered / totalDistance) * 100).toFixed(1);

                updateTransportProgress(transportId, {
                    distanceCovered: distanceCovered,
                    totalDistance: totalDistance
                });

                const destination = L.latLng(parseFloat(cfg.arriveeLat), parseFloat(cfg.arriveeLon));
                const distanceToDestination = latlng.distanceTo(destination) / 1000;

                if (distanceToDestination < 0.1) { // Within 100 meters
                    updateTransportStatus(transportId, 'Complété');
                    showModal(`Transport #${transportId} est arrivé à destination.`);
                    resetSimulationState(transportId);
                }
            }
        }

        map.panTo(latlng, { animate: true, duration: 0.5 });
    }

    function calculateDistanceCovered(currentPosition, route) {
        if (!route || !route.coordinates || route.coordinates.length === 0) {
            return 0;
        }

        let minDistance = Infinity;
        let closestPointIndex = 0;

        for (let i = 0; i < route.coordinates.length; i++) {
            const routePoint = L.latLng(route.coordinates[i][0], route.coordinates[i][1]);
            const distance = L.latLng(currentPosition).distanceTo(routePoint);
            if (distance < minDistance) {
                minDistance = distance;
                closestPointIndex = i;
            }
        }

        let distanceCovered = 0;
        for (let i = 1; i <= closestPointIndex; i++) {
            const point1 = L.latLng(route.coordinates[i-1][0], route.coordinates[i-1][1]);
            const point2 = L.latLng(route.coordinates[i][0], route.coordinates[i][1]);
            distanceCovered += point1.distanceTo(point2);
        }

        return distanceCovered / 1000; // Convert to km
    }

    function updateTransportProgress(transportId, data) {
        const row = document.querySelector(`.transport-row[data-transport-id="${transportId}"]`);
        if (row) {
            const progressCell = row.querySelector('.transport-progress');
            if (progressCell) {
                const pct = ((data.distanceCovered / data.totalDistance) * 100).toFixed(1);
                progressCell.innerHTML = `
                    ${pct}%<div class="progress"><div class="progress-bar" role="progressbar" style="width: ${pct}%" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100"></div></div>
                `;
            }
        }
    }

    function updateTransportStatus(transportId, status) {
        const row = document.querySelector(`.transport-row[data-transport-id="${transportId}"]`);
        if (row) {
            const statusCell = row.querySelector('.badge');
            if (statusCell) {
                statusCell.textContent = status;
                statusCell.className = `badge ${
                    status === 'Complété' ? 'text-dark border border-dark' :
                    status === 'Actif' ? 'text-orange border border-orange' :
                    'text-muted border border-muted'
                }`;
            }
        }
    }

    function resetSimulationState(transportId) {
        isMercureInitialized = false;
        if (eventSource) {
            eventSource.close();
            eventSource = null;
            window.mercureEventSource = null;
        }
        if (transportMarkers[transportId]) {
            map.removeLayer(transportMarkers[transportId]);
            delete transportMarkers[transportId];
        }
    }

    function showModal(message) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Information</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">${message}</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    }

    function showLoading() {
        if (!document.getElementById('loadingSpinner')) {
            const spinner = document.createElement('div');
            spinner.id = 'loadingSpinner';
            spinner.className = 'position-fixed top-50 start-50 translate-middle bg-white p-3 rounded shadow-lg d-flex flex-column align-items-center';
            spinner.innerHTML = `
                <div class="spinner-border text-primary mb-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span>Initialisation du suivi...</span>
            `;
            document.body.appendChild(spinner);
        } else {
            document.getElementById('loadingSpinner').className = document.getElementById('loadingSpinner').className.replace('hidden', '');
        }
    }

    window.addEventListener('beforeunload', () => {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
            window.mercureEventSource = null;
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            console.log('Page hidden, pausing real-time updates');
        } else {
            console.log('Page visible, resuming real-time updates');
            if (isMercureInitialized && !eventSource) {
                const activeTransports = cfgs.map(c => c.transportId);
                if (activeTransports.length > 0) {
                    initializeMercureConnection(activeTransports)
                        .then(() => console.log('Reconnected to Mercure'))
                        .catch(err => console.error('Failed to reconnect to Mercure:', err));
                }
            }
        }
    });
});