

import Alpine from 'alpinejs';
import * as bootstrap from 'bootstrap';
import $ from 'jquery';
import Swal from 'sweetalert2';
import { createIcons, icons } from 'lucide';
import ApexCharts from 'apexcharts';
import './validation.js';
import './searchable-select.js';
import './india-address.js';

window.Alpine = Alpine;
window.bootstrap = bootstrap;
window.$ = window.jQuery = $;
window.Swal = Swal;
window.ApexCharts = ApexCharts;
window.refreshIcons = () => createIcons({ icons });

Alpine.start();

document.addEventListener('DOMContentLoaded', window.refreshIcons);
