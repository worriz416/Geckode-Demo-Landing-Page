/*
 * File: js/demo.js
 * Description: Initializes the public demo using the shared Geckode block editor engine.
 * Primary Owner: Liam Klein
 * Team: Team 418
 */

/**
 * Initializes the public demo once the page markup has loaded.
 *
 * @returns {void}
 */
function initializeDemo() {
  const container = document.getElementById("container");
  const spawnSidebar = document.getElementById("spawn-sidebar");
  const trashZone = document.getElementById("trash-zone");
  let nextId = 3;
  const palette = [
    { label: "Move", color: "#5ab576" },
    { label: "Jump", color: "#6C63FF" },
    { label: "Glow", color: "#FF6584" },
    { label: "Score", color: "#F7BA72" },
    { label: "Loop", color: "#8d69e2" }
  ];

  if (!container || !spawnSidebar || !trashZone || !window.GeckodeBlockEditor) {
    return;
  }

  window.GeckodeBlockEditor.create({
    container: container,
    trashZone: trashZone,
    spawnTarget: spawnSidebar,
    spawnButtonClassName: "btn",
    canEdit: true,
    palette: palette,
    soundUrls: {
      pickup: "./assets/pop-high.mp3",
      drop: "./assets/pop-low.mp3"
    },
    initialBlocks: [
      { id: "card-0", label: "Start", color: "#5ab576", posLeft: 40, posTop: 40 },
      { id: "card-1", label: "Glow", color: "#6C63FF", posLeft: 180, posTop: 120 },
      { id: "card-2", label: "Score", color: "#FF6584", posLeft: 320, posTop: 200 }
    ],
    onCreateBlock: function (color, suggestedPosition, currentState, label) {
      const block = {
        id: "card-" + nextId,
        label: label + " " + nextId,
        color: color,
        posLeft: suggestedPosition.left,
        posTop: suggestedPosition.top
      };

      nextId += 1;
      return block;
    },
    onCommit: function () {
      return true;
    }
  });
}

window.addEventListener("DOMContentLoaded", initializeDemo);
