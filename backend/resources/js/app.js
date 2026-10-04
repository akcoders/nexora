

import Alpine from 'alpinejs';
import * as bootstrap from 'bootstrap';
import $ from 'jquery';
import Swal from 'sweetalert2';
import { createIcons, icons } from 'lucide';
import ApexCharts from 'apexcharts';

window.Alpine = Alpine;
window.bootstrap = bootstrap;
window.$ = window.jQuery = $;
window.Swal = Swal;
window.ApexCharts = ApexCharts;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => createIcons({ icons }));
