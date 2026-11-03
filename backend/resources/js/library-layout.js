/**
 * Layout helpers shared by the knowledge library pages.
 */

/**
 * The side column follows the reader. When it is taller than the window it scrolls with the
 * page until its last card is in view and only then sticks, so nothing in it is ever out of reach.
 */
export function followViewport(column) {
    if (!column) return;
    const update = () => {
        if (window.matchMedia('(max-width: 991.98px)').matches) {
            column.style.top = '';
            return;
        }
        const topbar = parseFloat(window.getComputedStyle(document.documentElement).getPropertyValue('--nx-topbar')) || 60;
        const offset = topbar + 16;
        const fits = column.offsetHeight <= window.innerHeight - offset - 16;
        column.style.top = `${fits ? offset : window.innerHeight - column.offsetHeight - 16}px`;
    };
    update();
    window.addEventListener('resize', update);
    if ('ResizeObserver' in window) new ResizeObserver(update).observe(column);
}
