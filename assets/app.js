// assets/app.js
import $ from 'jquery';
window.jQuery = window.$ = $; // Rend jQuery global pour les plugins

import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

// Importer FlexSlider depuis node_modules
import './css/flex-slider.css'; // Remplace ./css/flex-slider.css
import 'flexslider/jquery.flexslider-min.js'; // Si tu utilises FlexSlider JS

import './css/animate.css';
import './css/owl.css';
import './css/fontawesome.css';
import './css/templatemo-villa-agency.css';
import './css/meuble-style.css';
import './js/counter.js';
import './js/custom.js';
import './js/isotope.min.js';
import './js/owl-carousel.js';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');