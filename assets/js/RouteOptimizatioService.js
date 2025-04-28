import OpenRouteService from 'openrouteservice-js';

export class RouteOptimizationService {
  constructor(apiKey) {
    this.orsOptimization = new OpenRouteService.Optimization({ api_key: apiKey });
  }

  /**
   * Optimize a route with multiple stops
   * @param {Array} stops - Array of [lat, lng] coordinates for all stops
   * @param {Array} vehicleStart - [lat, lng] coordinates for vehicle start position
   * @param {Array} vehicleEnd - [lat, lng] coordinates for vehicle end position (optional)
   * @returns {Promise<Object>} - Optimized route data
   */
  async optimizeRoute(stops, vehicleStart, vehicleEnd = vehicleStart) {
    // Format stops as jobs for the API
    const jobs = stops.map((stop, index) => ({
      id: index + 1,
      service: 300, // 5 minutes service time
      amount: [1],
      location: [stop[1], stop[0]] // Convert [lat, lng] to [lng, lat] for ORS API
    }));

    // Configure vehicle
    const vehicle = {
      id: 1,
      profile: 'driving-car',
      start: [vehicleStart[1], vehicleStart[0]], // Convert to [lng, lat]
      end: [vehicleEnd[1], vehicleEnd[0]], // Convert to [lng, lat]
      capacity: [jobs.length], // Vehicle capacity matches number of stops
      skills: [1] // Default skill
    };

    // Request optimization
    try {
      const response = await this.orsOptimization.calculate({
        jobs: jobs,
        vehicles: [vehicle]
      });

      // Process response to match our expected format
      if (!response.routes || !response.routes[0]) {
        throw new Error('No optimized route found');
      }

      const route = response.routes[0];
      const steps = route.steps || [];

      // Convert coordinates back to [lat, lng] format for display
      const coordinates = steps.map(step => [
        step.location[1], // lat
        step.location[0]  // lng
      ]);

      return {
        coordinates: coordinates,
        distance: route.distance || 0,
        duration: route.duration || 0,
        steps: steps.map(step => ({
          type: step.type || 'job',
          location: [step.location[1], step.location[0]],
          arrival: step.arrival || 0,
          duration: step.duration || 0
        }))
      };
    } catch (error) {
      console.error('ORS Optimization API error:', error);
      throw new Error(`Failed to optimize route: ${error.message}`);
    }
  }

  /**
   * Get a simple route between two points
   * @param {number} fromLat - Starting latitude
   * @param {number} fromLng - Starting longitude
   * @param {number} toLat - Destination latitude
   * @param {number} toLng - Destination longitude
   * @returns {Promise<Object>} - Route data
   */
  async getRoute(fromLat, fromLng, toLat, toLng) {
    const orsDirections = new OpenRouteService.Directions({ api_key: this.apiKey });
    
    try {
      const response = await orsDirections.calculate({
        coordinates: [
          [fromLng, fromLat],
          [toLng, toLat]
        ],
        profile: 'driving-car',
        format: 'geojson'
      });
      
      return response;
    } catch (error) {
      console.error('ORS Directions API error:', error);
      throw new Error(`Failed to get route: ${error.message}`);
    }
  }
}