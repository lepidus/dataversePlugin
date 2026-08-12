export function conditionalField(baseComponentName, isVisible) {
    const baseComponent = pkp.registry.getComponent(baseComponentName);

    return {
        extends: baseComponent,
        render(...args) {
            if (!isVisible(this)) {
                return null;
            }
            return baseComponent.render.apply(this, args);
        },
    };
}
