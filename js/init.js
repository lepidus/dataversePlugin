const javaScriptPath = document.currentScript.src.substring(0, document.currentScript.src.lastIndexOf('/') + 1);
const importsScripts = ["study.js"];

function initializeScripts() {
    loadScripts(importsScripts);
}

function loadScripts(scripts) {
    if (scripts.length > 0) {
        const script = scripts.pop();
        $.getScript(javaScriptPath + script, () => loadScripts(scripts))
    } else document.dispatchEvent(new Event("dataverse-plugin-scripts-loaded"));
}

$(document).ready(initializeScripts);
