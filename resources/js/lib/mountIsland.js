import { createApp } from 'vue';

export default function mountIsland(mountId, Component, extraProps = {}) {
    const el = document.getElementById(mountId);
    if (!el) {
        return null;
    }
    let props = {};
    if (el.dataset.props) {
        try {
            props = JSON.parse(el.dataset.props);
        } catch {
            props = {};
        }
    }
    const app = createApp(Component, { ...props, ...extraProps });
    app.mount(el);
    return app;
}
