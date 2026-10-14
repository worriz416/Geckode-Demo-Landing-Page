/*
 * File: js/project-editor.js
 * Description: Initializes the saved project editor using the shared Geckode block editor engine and persistence APIs.
 * Primary Owner: Liam Klein
 * Team: Team 418
 */

/**
 * Initializes the saved-project editor once the server-rendered config is available.
 *
 * @returns {void}
 */
function initializeProjectEditor() {
  const configNode = document.getElementById("project-editor-config");
  let pageConfig;
  let container;
  let trashZone;
  let spawnTarget;

  if (!configNode || !window.GeckodeBlockEditor) {
    return;
  }

  pageConfig = JSON.parse(configNode.textContent);
  container = document.getElementById(pageConfig.containerId);
  trashZone = document.getElementById(pageConfig.trashZoneId);
  spawnTarget = pageConfig.spawnTargetId ? document.getElementById(pageConfig.spawnTargetId) : null;

  if (!container || !trashZone) {
    return;
  }

  window.GeckodeBlockEditor.create({
    container: container,
    trashZone: trashZone,
    spawnTarget: spawnTarget,
    spawnButtonClassName: "spawn-btn",
    canEdit: !!pageConfig.canEdit,
    palette: pageConfig.palette,
    soundUrls: pageConfig.soundUrls,
    initialBlocks: buildInitialBlocks(pageConfig.blocks, pageConfig.connections),
    onCreateBlock: function (color, suggestedPosition) {
      return createProjectBlock(pageConfig.projectId, color, suggestedPosition);
    },
    onDeleteBlocks: function (blocks) {
      return deleteProjectBlocks(pageConfig.projectId, blocks);
    },
    onCommit: function (state) {
      return saveProjectState(pageConfig.projectId, state);
    }
  });
}

window.addEventListener("DOMContentLoaded", initializeProjectEditor);

/**
 * Converts saved block and connection rows into the shared editor's linear chain format.
 *
 * @param {Array<Object>} rawBlocks Project block rows fetched from PHP.
 * @param {Array<Object>} rawConnections Project connection rows fetched from PHP.
 * @returns {Array<Object>} Normalized initial block objects for the shared editor core.
 */
function buildInitialBlocks(rawBlocks, rawConnections) {
  const blockMap = new Map();
  const adjacency = new Map();
  const visited = new Set();
  const initialBlocks = [];

  rawBlocks.forEach(function (block) {
    const id = "block-" + block.block_id;
    blockMap.set(id, {
      id: id,
      label: block.block_label,
      color: block.block_color,
      databaseId: Number(block.block_id),
      posLeft: Number(block.position_left || 0),
      posTop: Number(block.position_top || 0),
      leftId: null
    });
    adjacency.set(id, new Set());
  });

  rawConnections.forEach(function (connection) {
    const leftId = "block-" + connection.block_id_1;
    const rightId = "block-" + connection.block_id_2;

    if (!adjacency.has(leftId) || !adjacency.has(rightId)) {
      return;
    }

    adjacency.get(leftId).add(rightId);
    adjacency.get(rightId).add(leftId);
  });

  blockMap.forEach(function (block, blockId) {
    const component = [];
    let pending;

    if (visited.has(blockId)) {
      return;
    }

    pending = [blockId];

    while (pending.length > 0) {
      const currentId = pending.pop();

      if (visited.has(currentId) || !blockMap.has(currentId)) {
        continue;
      }

      visited.add(currentId);
      component.push(blockMap.get(currentId));
      adjacency.get(currentId).forEach(function (neighborId) {
        if (!visited.has(neighborId)) {
          pending.push(neighborId);
        }
      });
    }

    component
      .sort(function (a, b) {
        if (a.posLeft !== b.posLeft) {
          return a.posLeft - b.posLeft;
        }

        if (a.posTop !== b.posTop) {
          return a.posTop - b.posTop;
        }

        return a.databaseId - b.databaseId;
      })
      .forEach(function (componentBlock, index) {
        if (index > 0) {
          componentBlock.leftId = component[index - 1].id;
        }
      });
  });

  blockMap.forEach(function (block) {
    initialBlocks.push(block);
  });

  return initialBlocks;
}

/**
 * Creates a new persisted project block through the existing add-block API.
 *
 * @param {number} projectId Project identifier being edited.
 * @param {string} color Hex color selected for the new block.
 * @param {{left: number, top: number}} suggestedPosition Deterministic placement from the editor core.
 * @returns {Promise<Object>} Normalized block object for immediate rendering in the editor.
 */
async function createProjectBlock(projectId, color, suggestedPosition) {
  const response = await fetch("api/add-project-block.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      projectId: projectId,
      color: color,
      label: "Block",
      posLeft: suggestedPosition.left,
      posTop: suggestedPosition.top
    })
  });
  const data = await response.json();

  if (!response.ok || !data.success) {
    throw new Error(data.error || "Failed to create block.");
  }

  return {
    id: data.id,
    label: "Block",
    color: color,
    databaseId: Number(data.blockId),
    posLeft: suggestedPosition.left,
    posTop: suggestedPosition.top
  };
}

/**
 * Deletes one or more persisted project blocks through the existing delete API.
 *
 * @param {number} projectId Project identifier being edited.
 * @param {Array<Object>} blocks Blocks selected for deletion.
 * @returns {Promise<boolean>} True when every delete request succeeds, otherwise false.
 */
async function deleteProjectBlocks(projectId, blocks) {
  const results = await Promise.all(
    blocks.map(async function (block) {
      let response;
      let data;

      if (!block.databaseId) {
        return true;
      }

      response = await fetch("api/delete-project-block.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          projectId: projectId,
          blockId: block.databaseId
        })
      });
      data = await response.json();
      return response.ok && !!data.success;
    })
  );

  return results.every(Boolean);
}

/**
 * Saves the current block positions and adjacent chain links for a project.
 *
 * @param {number} projectId Project identifier being edited.
 * @param {{blocks: Array<Object>, connections: Array<Object>}} state Normalized editor state from the shared core.
 * @returns {Promise<void>} Resolves when the server accepts the updated project state.
 */
async function saveProjectState(projectId, state) {
  const response = await fetch("api/save-project-state.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      projectId: projectId,
      blocks: state.blocks.map(function (block) {
        return {
          databaseId: block.databaseId,
          label: block.label,
          color: block.color,
          posLeft: block.posLeft,
          posTop: block.posTop
        };
      }),
      connections: state.connections
        .filter(function (connection) {
          return connection.leftDatabaseId && connection.rightDatabaseId;
        })
        .map(function (connection) {
          return {
            leftDatabaseId: connection.leftDatabaseId,
            rightDatabaseId: connection.rightDatabaseId
          };
        })
    })
  });
  const data = await response.json();

  if (!response.ok || !data.success) {
    throw new Error(data.error || "Failed to save project state.");
  }
}
