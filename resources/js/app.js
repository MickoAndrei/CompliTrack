import './bootstrap';
import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';
import './pages';

window.Alpine = Alpine;

const renderIcons = () => createIcons({ icons });
window.renderIcons = renderIcons;

document.addEventListener('DOMContentLoaded', renderIcons);
document.addEventListener('alpine:initialized', renderIcons);
Alpine.start();
