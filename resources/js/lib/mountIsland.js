import { createApp } from 'vue';

/** Parse an island element's `data-props` JSON, tolerating absent/invalid values. */
export function readProps(el) {
    if (!el.dataset.props) {
        return {};
    }
    try {
        return JSON.parse(el.dataset.props);
    } catch {
        return {};
    }
}

export default function mountIsland(mountId, Component, extraProps = {}) {
    const el = document.getElementById(mountId);
    if (!el) {
        return null;
    }
    const app = createApp(Component, { ...readProps(el), ...extraProps });
    app.mount(el);
    return app;
}
