/*
 * File: js/block-editor-core.js
 * Description: Shared block editor engine used by both the public demo and the saved project editor.
 * Primary Owners: Liam Klein and Team 418
 * Team: Team 418
 */

(function () {
  /**
   * Creates a shared block editor instance inside the supplied DOM elements.
   *
   * @param {Object} config Editor configuration and callback hooks.
   * @returns {{getState: function(): Object, addBlock: function(Object): void}} Public editor API.
   */
  function create(config) {
    if (!config || !config.container || !config.trashZone || !Array.isArray(config.initialBlocks)) {
      throw new Error("GeckodeBlockEditor.create requires container, trashZone, and initialBlocks.");
    }

    const container = config.container;
    const trashZone = config.trashZone;
    const spawnTarget = config.spawnTarget || null;
    const canEdit = !!config.canEdit;
    const palette = Array.isArray(config.palette) ? config.palette : [];
    const spawnButtonClassName = config.spawnButtonClassName || "btn";
    const sounds = createSounds(config.soundUrls || {});

    const cards = new Map();
    const leftNeighbor = new Map();
    const rightNeighbor = new Map();
    let dragState = null;
    let localSequence = 1;

    const snapIndicator = document.createElement("div");
    snapIndicator.className = "snap-indicator";
    container.appendChild(snapIndicator);

    initializeBlocks(config.initialBlocks);
    renderSpawnButtons();

    return {
      getState: buildState,
      addBlock: function (block) {
        addBlock(block, true);
      }
    };

    /**
     * Creates optional pickup and drop audio elements for editor feedback.
     *
     * @param {{pickup?: string, drop?: string}} soundUrls Audio file paths keyed by action name.
     * @returns {{pickup: HTMLAudioElement|null, drop: HTMLAudioElement|null}} Prepared sound instances.
     */
    function createSounds(soundUrls) {
      const pickup = soundUrls.pickup ? new Audio(soundUrls.pickup) : null;
      const drop = soundUrls.drop ? new Audio(soundUrls.drop) : null;

      if (pickup) {
        pickup.volume = 0.1;
      }

      if (drop) {
        drop.volume = 0.15;
      }

      return {
        pickup: pickup,
        drop: drop
      };
    }

    /**
     * Plays a sound effect when one is configured for the current editor instance.
     *
     * @param {HTMLAudioElement|null} sound Audio element to play.
     * @returns {void}
     */
    function playSound(sound) {
      if (!sound) {
        return;
      }

      sound.currentTime = 0;
      sound.play().catch(function () {});
    }

    /**
     * Renders the initial set of blocks and recreates any saved left-to-right links.
     *
     * @param {Array<Object>} initialBlocks Normalized initial block records.
     * @returns {void}
     */
    function initializeBlocks(initialBlocks) {
      initialBlocks.forEach(function (block) {
        addBlock(block, false);
      });

      initialBlocks.forEach(function (block) {
        const cardId = String(block.id);
        const leftId = block.leftId ? String(block.leftId) : null;

        if (!leftId || !cards.has(cardId) || !cards.has(leftId)) {
          return;
        }

        leftNeighbor.set(cardId, leftId);
        rightNeighbor.set(leftId, cardId);
      });

      refreshConnectedClasses();
    }

    /**
     * Builds the sidebar spawn buttons from the configured block palette.
     *
     * @returns {void}
     */
    function renderSpawnButtons() {
      if (!canEdit || !spawnTarget || palette.length === 0) {
        return;
      }

      spawnTarget.textContent = "";

      palette.forEach(function (item) {
        const button = document.createElement("button");
        const label = typeof item === "string" ? "Block" : item.label || "Block";
        const color = typeof item === "string" ? item : item.color;

        button.type = "button";
        button.className = spawnButtonClassName;
        button.textContent = label;
        button.style.background = color;
        button.style.boxShadow = "0 12px 24px " + color + "33";
        button.addEventListener("click", function () {
          handleSpawn(color, label);
        });

        spawnTarget.appendChild(button);
      });
    }

    /**
     * Computes a deterministic placement for newly spawned blocks.
     *
     * @returns {{left: number, top: number}} Suggested pixel position within the editor canvas.
     */
    function getSuggestedPosition() {
      const index = cards.size;

      return {
        left: Math.min(32 + index * 20, Math.max(container.clientWidth - 120, 16)),
        top: Math.min(32 + index * 24, Math.max(container.clientHeight - 90, 16))
      };
    }

    /**
     * Creates a new block through the configured creation hook and commits the result.
     *
     * @param {string} color Hex color for the new block.
     * @param {string} label Display label for the new block.
     * @returns {Promise<void>} Resolves after the block is rendered and any commit callback finishes.
     */
    async function handleSpawn(color, label) {
      let block;
      let suggestedPosition;

      if (!canEdit) {
        return;
      }

      suggestedPosition = getSuggestedPosition();
      block = config.onCreateBlock
        ? await Promise.resolve(config.onCreateBlock(color, suggestedPosition, buildState(), label))
        : {
            id: "local-" + localSequence++,
            label: label,
            color: color,
            posLeft: suggestedPosition.left,
            posTop: suggestedPosition.top
          };

      if (!block) {
        return;
      }

      if (block.posLeft == null) {
        block.posLeft = suggestedPosition.left;
      }

      if (block.posTop == null) {
        block.posTop = suggestedPosition.top;
      }

      addBlock(block, true);
      await emitCommit();
    }

    /**
     * Adds one block element to the DOM and internal neighbor maps.
     *
     * @param {Object} block Raw or normalized block data.
     * @param {boolean} refresh Whether connected styling should be recalculated immediately.
     * @returns {void}
     */
    function addBlock(block, refresh) {
      const normalized = normalizeBlock(block);
      const element = document.createElement("div");

      element.className = "card";
      element.id = normalized.id;
      element.textContent = normalized.label;
      element.style.background = normalized.color;
      element.style.left = normalized.posLeft + "px";
      element.style.top = normalized.posTop + "px";
      element.dataset.cardId = normalized.id;

      element.addEventListener("mouseenter", handleMouseEnter);
      element.addEventListener("mouseleave", handleMouseLeave);

      if (canEdit) {
        element.addEventListener("pointerdown", handlePointerDown);
      }

      normalized.element = element;
      cards.set(normalized.id, normalized);
      leftNeighbor.set(normalized.id, null);
      rightNeighbor.set(normalized.id, null);
      container.appendChild(element);

      if (refresh) {
        refreshConnectedClasses();
      }
    }

    /**
     * Normalizes block data into the internal editor representation.
     *
     * @param {Object} block Raw block data from a wrapper or saved state.
     * @returns {{id: string, label: string, color: string, posLeft: number, posTop: number, databaseId: number|null}} Normalized block object.
     */
    function normalizeBlock(block) {
      return {
        id: String(block.id || "local-" + localSequence++),
        label: block.label || "Block",
        color: block.color || "#5ab576",
        posLeft: Number(block.posLeft || 0),
        posTop: Number(block.posTop || 0),
        databaseId: block.databaseId == null ? null : Number(block.databaseId)
      };
    }

    /**
     * Serializes the current editor layout into blocks and adjacent connections.
     *
     * @returns {{blocks: Array<Object>, connections: Array<Object>}} Current editor state.
     */
    function buildState() {
      const blocks = [];
      const connections = [];

      cards.forEach(function (card) {
        blocks.push({
          id: card.id,
          label: card.label,
          color: card.color,
          databaseId: card.databaseId,
          posLeft: card.element.offsetLeft,
          posTop: card.element.offsetTop
        });
      });

      rightNeighbor.forEach(function (rightId, leftId) {
        let leftCard;
        let rightCard;

        if (!rightId || !cards.has(leftId) || !cards.has(rightId)) {
          return;
        }

        leftCard = cards.get(leftId);
        rightCard = cards.get(rightId);
        connections.push({
          leftId: leftId,
          rightId: rightId,
          leftDatabaseId: leftCard.databaseId,
          rightDatabaseId: rightCard.databaseId
        });
      });

      return {
        blocks: blocks,
        connections: connections
      };
    }

    /**
     * Updates connected and hover styling classes for every rendered block.
     *
     * @returns {void}
     */
    function refreshConnectedClasses() {
      cards.forEach(function (card, cardId) {
        const isConnected = leftNeighbor.get(cardId) !== null || rightNeighbor.get(cardId) !== null;
        card.element.classList.toggle("connected", isConnected);
        card.element.classList.remove("chain-hover");
      });
    }

    /**
     * Collects the dragged chain starting at the given block and continuing rightward.
     *
     * @param {string} startId Starting block identifier.
     * @returns {Array<string>} Ordered block identifiers in the chain.
     */
    function getCardChain(startId) {
      const chain = [];
      let currentId = startId;

      while (currentId && cards.has(currentId)) {
        chain.push(currentId);
        currentId = rightNeighbor.get(currentId);
      }

      return chain;
    }

    /**
     * Finds every block connected to the given block in either direction.
     *
     * @param {string} startId Starting block identifier.
     * @returns {Set<string>} Connected block identifiers.
     */
    function getConnectedSet(startId) {
      const visited = new Set();
      const pending = [startId];

      while (pending.length > 0) {
        const currentId = pending.pop();

        if (!currentId || visited.has(currentId) || !cards.has(currentId)) {
          continue;
        }

        visited.add(currentId);
        pending.push(leftNeighbor.get(currentId));
        pending.push(rightNeighbor.get(currentId));
      }

      return visited;
    }

    /**
     * Detaches a block from any immediate left neighbor before a drag begins.
     *
     * @param {string} cardId Block identifier being dragged.
     * @returns {void}
     */
    function detachFromLeft(cardId) {
      const leftId = leftNeighbor.get(cardId);

      if (!leftId) {
        return;
      }

      rightNeighbor.set(leftId, null);
      leftNeighbor.set(cardId, null);
    }

    /**
     * Moves a dragged chain while keeping the full group inside the editor bounds.
     *
     * @param {Array<string>} chainIds Ordered block identifiers in the dragged chain.
     * @param {number} dx Requested horizontal movement in pixels.
     * @param {number} dy Requested vertical movement in pixels.
     * @returns {void}
     */
    function updateChainPositions(chainIds, dx, dy) {
      const bounds = {
        minLeft: Infinity,
        minTop: Infinity,
        maxRight: -Infinity,
        maxBottom: -Infinity
      };
      let safeDx = dx;
      let safeDy = dy;

      chainIds.forEach(function (cardId) {
        const card = cards.get(cardId);

        bounds.minLeft = Math.min(bounds.minLeft, card.element.offsetLeft);
        bounds.minTop = Math.min(bounds.minTop, card.element.offsetTop);
        bounds.maxRight = Math.max(bounds.maxRight, card.element.offsetLeft + card.element.offsetWidth);
        bounds.maxBottom = Math.max(bounds.maxBottom, card.element.offsetTop + card.element.offsetHeight);
      });

      if (bounds.minLeft + safeDx < 0) {
        safeDx = -bounds.minLeft;
      }

      if (bounds.maxRight + safeDx > container.clientWidth) {
        safeDx = container.clientWidth - bounds.maxRight;
      }

      if (bounds.minTop + safeDy < 0) {
        safeDy = -bounds.minTop;
      }

      if (bounds.maxBottom + safeDy > container.clientHeight) {
        safeDy = container.clientHeight - bounds.maxBottom;
      }

      chainIds.forEach(function (cardId) {
        const card = cards.get(cardId);

        card.element.style.left = card.element.offsetLeft + safeDx + "px";
        card.element.style.top = card.element.offsetTop + safeDy + "px";
      });
    }

    /**
     * Finds the best available snap target to the left of the dragged chain.
     *
     * @param {Array<string>} chainIds Ordered block identifiers in the dragged chain.
     * @returns {{candidateId: string, left: number, top: number, distance: number}|null} Best snap target, or null when none qualifies.
     */
    function getSnapTarget(chainIds) {
      const leadId = chainIds[0];
      const leadCard = cards.get(leadId);
      const leadRect = leadCard.element.getBoundingClientRect();
      const chainSet = new Set(chainIds);
      const snapDistance = 44;
      let bestTarget = null;

      cards.forEach(function (candidate, candidateId) {
        let candidateRect;
        let verticalDistance;
        let horizontalDistance;

        if (chainSet.has(candidateId) || rightNeighbor.get(candidateId) !== null) {
          return;
        }

        candidateRect = candidate.element.getBoundingClientRect();
        verticalDistance = Math.abs(leadRect.top - candidateRect.top);
        horizontalDistance = Math.abs(leadRect.left - candidateRect.right);

        if (horizontalDistance > snapDistance || verticalDistance > snapDistance) {
          return;
        }

        if (!bestTarget || horizontalDistance + verticalDistance < bestTarget.distance) {
          bestTarget = {
            candidateId: candidateId,
            left: candidate.element.offsetLeft + candidate.element.offsetWidth,
            top: candidate.element.offsetTop,
            distance: horizontalDistance + verticalDistance
          };
        }
      });

      return bestTarget;
    }

    /**
     * Shows or hides the snap indicator for the currently dragged chain.
     *
     * @param {{left: number, top: number}|null} target Current snap target position, or null.
     * @param {Array<string>} chainIds Ordered block identifiers in the dragged chain.
     * @returns {void}
     */
    function updateSnapIndicator(target, chainIds) {
      let totalWidth = 0;
      let maxHeight = 0;

      if (!target) {
        snapIndicator.style.display = "none";
        return;
      }

      chainIds.forEach(function (cardId) {
        const card = cards.get(cardId);

        totalWidth += card.element.offsetWidth;
        maxHeight = Math.max(maxHeight, card.element.offsetHeight);
      });

      snapIndicator.style.left = target.left + "px";
      snapIndicator.style.top = target.top + "px";
      snapIndicator.style.width = totalWidth + "px";
      snapIndicator.style.height = maxHeight + "px";
      snapIndicator.style.display = "block";
    }

    /**
     * Checks whether any block in the dragged chain overlaps the trash zone.
     *
     * @param {Array<string>} chainIds Ordered block identifiers in the dragged chain.
     * @returns {boolean} True when the dragged chain overlaps the trash area.
     */
    function isChainOverTrash(chainIds) {
      const trashRect = trashZone.getBoundingClientRect();

      return chainIds.some(function (cardId) {
        const rect = cards.get(cardId).element.getBoundingClientRect();

        return !(
          rect.right < trashRect.left ||
          rect.left > trashRect.right ||
          rect.bottom < trashRect.top ||
          rect.top > trashRect.bottom
        );
      });
    }

    /**
     * Refreshes the trash-zone active state based on the dragged chain position.
     *
     * @param {Array<string>} chainIds Ordered block identifiers in the dragged chain.
     * @returns {boolean} True when the trash zone is active for the chain.
     */
    function updateTrashState(chainIds) {
      const isActive = isChainOverTrash(chainIds);
      trashZone.classList.toggle("active", isActive);
      return isActive;
    }

    /**
     * Snaps a dragged chain onto a target block and rebuilds their adjacency.
     *
     * @param {string} targetId Block identifier receiving the new chain on its right side.
     * @param {Array<string>} chainIds Ordered block identifiers in the dragged chain.
     * @returns {void}
     */
    function attachChain(targetId, chainIds) {
      const leadId = chainIds[0];
      const leadCard = cards.get(leadId);
      const targetCard = cards.get(targetId);

      updateChainPositions(
        chainIds,
        targetCard.element.offsetLeft + targetCard.element.offsetWidth - leadCard.element.offsetLeft,
        targetCard.element.offsetTop - leadCard.element.offsetTop
      );
      rightNeighbor.set(targetId, leadId);
      leftNeighbor.set(leadId, targetId);
      refreshConnectedClasses();
    }

    /**
     * Removes a chain of blocks from the DOM and internal editor state.
     *
     * @param {Array<string>} chainIds Ordered block identifiers to delete.
     * @returns {void}
     */
    function removeChain(chainIds) {
      const firstId = chainIds[0];
      const leftId = leftNeighbor.get(firstId);

      if (leftId) {
        rightNeighbor.set(leftId, null);
      }

      chainIds.forEach(function (cardId) {
        const card = cards.get(cardId);

        if (card) {
          card.element.remove();
        }

        cards.delete(cardId);
        leftNeighbor.delete(cardId);
        rightNeighbor.delete(cardId);
      });

      refreshConnectedClasses();
    }

    /**
     * Highlights an entire connected chain when one of its blocks is hovered.
     *
     * @param {MouseEvent} event Hover event from a block element.
     * @returns {void}
     */
    function handleMouseEnter(event) {
      const connectedIds = getConnectedSet(event.currentTarget.dataset.cardId);

      connectedIds.forEach(function (cardId) {
        const card = cards.get(cardId);

        if (card) {
          card.element.classList.add("chain-hover");
        }
      });
    }

    /**
     * Clears chain hover styling when the pointer leaves a block.
     *
     * @param {MouseEvent} event Hover event from a block element.
     * @returns {void}
     */
    function handleMouseLeave(event) {
      const connectedIds = getConnectedSet(event.currentTarget.dataset.cardId);

      connectedIds.forEach(function (cardId) {
        const card = cards.get(cardId);

        if (card) {
          card.element.classList.remove("chain-hover");
        }
      });
    }

    /**
     * Starts dragging a block and any blocks chained to its right.
     *
     * @param {PointerEvent} event Pointer-down event from a draggable block.
     * @returns {void}
     */
    function handlePointerDown(event) {
      let cardId;
      let chainIds;

      if (!canEdit || dragState) {
        return;
      }

      event.preventDefault();
      cardId = event.currentTarget.dataset.cardId;
      chainIds = getCardChain(cardId);

      detachFromLeft(cardId);
      refreshConnectedClasses();
      playSound(sounds.pickup);

      dragState = {
        chainIds: chainIds,
        startX: event.clientX,
        startY: event.clientY,
        snapTarget: null,
        overTrash: false
      };

      chainIds.forEach(function (chainId, index) {
        const card = cards.get(chainId);

        card.element.classList.add("dragging");
        card.element.style.zIndex = String(20 - index);
      });

      document.addEventListener("pointermove", handlePointerMove);
      document.addEventListener("pointerup", handlePointerUp);
    }

    /**
     * Updates the dragged chain position, trash state, and snap target while moving.
     *
     * @param {PointerEvent} event Pointer-move event for the active drag.
     * @returns {void}
     */
    function handlePointerMove(event) {
      let dx;
      let dy;

      if (!dragState) {
        return;
      }

      dx = event.clientX - dragState.startX;
      dy = event.clientY - dragState.startY;
      dragState.startX = event.clientX;
      dragState.startY = event.clientY;

      updateChainPositions(dragState.chainIds, dx, dy);
      dragState.overTrash = updateTrashState(dragState.chainIds);
      dragState.snapTarget = dragState.overTrash ? null : getSnapTarget(dragState.chainIds);
      updateSnapIndicator(dragState.snapTarget, dragState.chainIds);
    }

    /**
     * Finalizes a drag by deleting, snapping, or committing the moved chain.
     *
     * @returns {Promise<void>} Resolves after any delete or commit work finishes.
     */
    async function handlePointerUp() {
      let deleted = false;
      let draggedIds;

      if (!dragState) {
        return;
      }

      draggedIds = dragState.chainIds.slice();

      if (dragState.overTrash) {
        deleted = await tryDeleteChain(draggedIds);
      } else if (dragState.snapTarget) {
        attachChain(dragState.snapTarget.candidateId, draggedIds);
      } else {
        refreshConnectedClasses();
      }

      playSound(sounds.drop);
      draggedIds.forEach(function (cardId) {
        const card = cards.get(cardId);

        if (card) {
          card.element.classList.remove("dragging");
          card.element.style.zIndex = "1";
        }
      });

      trashZone.classList.remove("active");
      snapIndicator.style.display = "none";
      dragState = null;

      document.removeEventListener("pointermove", handlePointerMove);
      document.removeEventListener("pointerup", handlePointerUp);

      if (!deleted || cards.size > 0) {
        await emitCommit();
      }
    }

    /**
     * Attempts to delete a dragged chain through the configured deletion hook.
     *
     * @param {Array<string>} chainIds Ordered block identifiers selected for deletion.
     * @returns {Promise<boolean>} True when the chain is removed, otherwise false.
     */
    async function tryDeleteChain(chainIds) {
      const blocksToDelete = chainIds
        .map(function (cardId) {
          return cards.get(cardId);
        })
        .filter(Boolean)
        .map(function (card) {
          return {
            id: card.id,
            label: card.label,
            color: card.color,
            databaseId: card.databaseId,
            posLeft: card.element.offsetLeft,
            posTop: card.element.offsetTop
          };
        });

      try {
        if (config.onDeleteBlocks) {
          const deleteResult = await Promise.resolve(config.onDeleteBlocks(blocksToDelete, buildState()));

          if (deleteResult === false) {
            refreshConnectedClasses();
            return false;
          }
        }
      } catch (error) {
        console.error("Delete failed:", error);
        refreshConnectedClasses();
        return false;
      }

      removeChain(chainIds);
      return true;
    }

    /**
     * Emits the current editor state through the configured commit callback.
     *
     * @returns {Promise<void>} Resolves after the commit callback settles.
     */
    async function emitCommit() {
      if (!canEdit || !config.onCommit) {
        return;
      }

      try {
        await Promise.resolve(config.onCommit(buildState()));
      } catch (error) {
        console.error("Commit failed:", error);
      }
    }
  }

  window.GeckodeBlockEditor = {
    create: create
  };
})();
