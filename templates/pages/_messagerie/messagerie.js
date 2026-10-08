(function () {
    var chatContainer = document.getElementById("chatMessagesContainer");
    if (!chatContainer) {
        return;
    }

    var chatForm = document.getElementById("chatInputForm");
    var chatInput = document.getElementById("chatInputText");
    var notifBtn = document.getElementById("chatNotificationBtn");
    var lastMessageId = 0;

    // Calculer le dernier ID présent dans le DOM
    var existingRows = document.querySelectorAll(".chat-message-row[data-id]");
    if (existingRows.length > 0) {
        lastMessageId = parseInt(existingRows[existingRows.length - 1].dataset.id || "0", 10);
    }

    function scrollToBottom() {
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    }

    // Scroll initial au bas de la conversation
    scrollToBottom();

    // Gestion de la cloche des notifications du navigateur
    if (notifBtn) {
        function updateNotifBtnState() {
            if (!("Notification" in window)) {
                notifBtn.classList.add("is-unsupported");
                notifBtn.title = "Notifications non supportées sur ce navigateur";
                return;
            }

            if (Notification.permission === "granted") {
                notifBtn.classList.add("is-active");
                notifBtn.classList.remove("is-denied");
                notifBtn.title = "Notifications activées (cliquez pour tester)";
            } else if (Notification.permission === "denied") {
                notifBtn.classList.add("is-denied");
                notifBtn.classList.remove("is-active");
                notifBtn.title = "Notifications bloquées dans les paramètres de votre navigateur";
            } else {
                notifBtn.classList.remove("is-active", "is-denied");
                notifBtn.title = "Activer les notifications du navigateur";
            }
        }

        updateNotifBtnState();

        notifBtn.addEventListener("click", function () {
            if (!("Notification" in window)) {
                if (typeof window.showToast === "function") {
                    window.showToast("Votre navigateur ne supporte pas les notifications.", "error");
                }
                return;
            }

            if (Notification.permission === "granted") {
                try {
                    new Notification("QuoiManger", {
                        body: "Les notifications de messagerie sont bien actives !",
                        icon: "/icons/icon.svg",
                    });
                } catch (e) {}
                if (typeof window.showToast === "function") {
                    window.showToast("Notifications de messagerie actives !", "success");
                }
            } else if (Notification.permission === "denied") {
                if (typeof window.showToast === "function") {
                    window.showToast("Les notifications sont bloquées dans les paramètres du navigateur.", "error");
                }
            } else {
                Notification.requestPermission().then(function (permission) {
                    updateNotifBtnState();
                    if (permission === "granted") {
                        try {
                            new Notification("QuoiManger", {
                                body: "Notifications de messagerie activées !",
                                icon: "/icons/icon.svg",
                            });
                        } catch (e) {}
                        if (typeof window.showToast === "function") {
                            window.showToast("Notifications de messagerie activées !", "success");
                        }
                    }
                });
            }
        });
    }

    // Envoi de message via Fetch
    if (chatForm && chatInput) {
        chatForm.addEventListener("submit", function (e) {
            e.preventDefault();
            var content = chatInput.value.trim();
            if (!content) return;

            chatInput.value = "";
            chatInput.focus();

            fetch("/messagerie/send", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                },
                body: new URLSearchParams({ content: content }),
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    if (data && data.success && data.message) {
                        appendMessage(data.message);
                        lastMessageId = Math.max(lastMessageId, data.message.id);
                        scrollToBottom();
                    }
                })
                .catch(function (err) {
                    console.error("Erreur envoi message:", err);
                });
        });
    }

    // Ajout d'une bulle de message dans le DOM
    function appendMessage(msg) {
        if (!chatContainer) return;
        var existing = document.querySelector('.chat-message-row[data-id="' + msg.id + '"]');
        if (existing) return;

        var row = document.createElement("div");
        row.className = "chat-message-row " + (msg.isMe ? "is-me" : "is-other");
        row.dataset.id = msg.id;

        if (!msg.isMe) {
            var sender = document.createElement("span");
            sender.className = "chat-sender-name";
            sender.textContent = msg.senderName;
            row.appendChild(sender);
        }

        var bubble = document.createElement("div");
        bubble.className = "chat-bubble" + (msg.proposition ? " chat-bubble-proposal" : "");

        if (msg.proposition) {
            bubble.innerHTML = renderProposalHtml(msg.proposition);
        } else {
            var p = document.createElement("p");
            p.className = "chat-bubble-text";
            p.textContent = msg.content;
            bubble.appendChild(p);
        }

        var meta = document.createElement("div");
        meta.className = "chat-bubble-meta";
        var time = document.createElement("span");
        time.className = "chat-bubble-time";
        time.textContent = msg.createdAt;
        meta.appendChild(time);
        bubble.appendChild(meta);

        row.appendChild(bubble);
        chatContainer.appendChild(row);
    }

    function renderProposalHtml(prop) {
        var isValidee = prop.status === "validee" || prop.status === "ajoutee_au_fil";
        var isAjoutee = prop.status === "ajoutee_au_fil";

        var html = '<div class="proposal-card-header">' +
            '<span class="proposal-tag-type">Proposition de repas</span>' +
            '<div class="proposal-header-right">' +
            '<span class="proposal-tag-moment">' + (prop.moment || "").toUpperCase() + '</span>';

        if (prop.canDelete) {
            html += '<form method="post" action="/messagerie/proposition/' + prop.id + '/supprimer" class="form-delete-proposal" data-confirm="Voulez-vous vraiment supprimer cette proposition de repas ?" data-confirm-title="Supprimer la proposition" data-confirm-btn="Supprimer">' +
                '<input type="hidden" name="_token" value="' + (prop.csrfToken || "") + '">' +
                '<button type="submit" class="btn-delete-proposal" title="Supprimer la proposition" aria-label="Supprimer la proposition">' +
                '<i class="fa-solid fa-trash-can"></i>' +
                '</button>' +
                '</form>';
        }

        html += '</div></div>' +
            '<h4 class="proposal-recipe-title">' + (prop.recetteNom || "") + '</h4>' +
            '<div class="proposal-recipe-date">Prévu pour le ' + (prop.dateRepas || "") + ' • par ' + (prop.proposePar || "") + '</div>';

        html += '<div class="proposal-votes-box" id="proposal-votes-' + prop.id + '">' +
            '<div class="proposal-votes-counts">' +
            '<span class="vote-count-pour">Pour (' + (prop.votesPour ? prop.votesPour.length : 0) + ')</span>' +
            '<span class="vote-count-contre">Contre (' + (prop.votesContre ? prop.votesContre.length : 0) + ')</span>' +
            '</div>';

        if (prop.votesPour && prop.votesPour.length > 0) {
            html += '<div class="proposal-voters-list">Pour : ' + prop.votesPour.join(", ") + '</div>';
        }
        html += '</div>';

        if (isAjoutee) {
            html += '<div class="proposal-status-banner is-ajoutee">Ajouté au fil des repas</div>';
        } else if (isValidee) {
            html += '<div class="proposal-status-banner is-validee">Validée par la famille !</div>' +
                '<form method="post" action="/messagerie/valider-au-fil/' + prop.id + '">' +
                '<button type="submit" class="btn-valider-au-fil">Ajouter au fil des repas</button>' +
                '</form>';
        } else {
            html += '<div class="proposal-vote-actions">' +
                '<button type="button" class="btn-vote-pour ' + (prop.myVote === "pour" ? "active" : "") + '" onclick="voteProposition(' + prop.id + ', \'pour\', this)">' +
                'Valider (Pour)' +
                '</button>' +
                '<button type="button" class="btn-vote-contre ' + (prop.myVote === "contre" ? "active" : "") + '" onclick="voteProposition(' + prop.id + ', \'contre\', this)">' +
                'Refuser (Contre)' +
                '</button>' +
                '</div>';
        }

        return html;
    }

    // Polling régulier temps réel si l'utilisateur est sur la page
    var pollTimer = null;
    var isPageUnloading = false;

    window.addEventListener("beforeunload", function () {
        isPageUnloading = true;
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
    });

    function scheduleNextPoll() {
        if (isPageUnloading || !document.getElementById("chatMessagesContainer")) {
            return;
        }
        var delay = document.hidden ? 10000 : 4000;
        pollTimer = setTimeout(pollNewMessages, delay);
    }

    function pollNewMessages() {
        if (isPageUnloading || !document.getElementById("chatMessagesContainer")) {
            return;
        }

        fetch("/messagerie/api/messages?since_id=" + lastMessageId, {
            headers: { Accept: "application/json" },
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (data && data.messages && data.messages.length > 0) {
                    var hasNew = false;
                    data.messages.forEach(function (msg) {
                        appendMessage(msg);
                        lastMessageId = Math.max(lastMessageId, msg.id);
                        hasNew = true;

                        // Notification système si l'onglet est masqué
                        if (document.hidden && !msg.isMe && "Notification" in window && Notification.permission === "granted") {
                            new Notification("QuoiManger - " + msg.senderName, {
                                body: msg.content || "Nouvelle proposition de repas !",
                                icon: "/icons/icon.svg",
                            });
                        }
                    });

                    if (hasNew) {
                        scrollToBottom();
                    }
                }
            })
            .catch(function (err) {
                // Silencieux
            })
            .finally(function () {
                scheduleNextPoll();
            });
    }

    scheduleNextPoll();

    // Fonction globale pour voter en AJAX
    window.voteProposition = function (propId, choix, btnElement) {
        fetch("/messagerie/vote/" + propId, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
            body: new URLSearchParams({ choix: choix }),
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    window.location.reload();
                }
            })
            .catch(function (err) {
                console.error("Erreur vote:", err);
            });
    };
})();
