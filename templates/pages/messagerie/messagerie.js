(function () {
    var chatContainer = document.getElementById("chatMessagesContainer");
    var chatForm = document.getElementById("chatInputForm");
    var chatInput = document.getElementById("chatInputText");
    var notifBtn = document.getElementById("chatNotificationBtn");
    var currentUserId = parseInt(document.body.dataset.userId || "0", 10);
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

    // Gestion de la permission des notifications PWA / Browser
    if (notifBtn) {
        if (!("Notification" in window) || Notification.permission === "granted") {
            notifBtn.style.display = "none";
        }

        notifBtn.addEventListener("click", function () {
            if ("Notification" in window) {
                Notification.requestPermission().then(function (permission) {
                    if (permission === "granted") {
                        notifBtn.style.display = "none";
                        new Notification("QuoiManger", {
                            body: "Notifications de messagerie activées !",
                            icon: "/icons/icon.svg",
                        });
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
            '<span class="proposal-tag-moment">' + (prop.moment || "").toUpperCase() + '</span>' +
            '</div>' +
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

    // Polling régulier temps réel toutes les 3 secondes
    function pollNewMessages() {
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
                // Silencieux pour éviter de polluer la console
            })
            .finally(function () {
                setTimeout(pollNewMessages, 3000);
            });
    }

    setTimeout(pollNewMessages, 3000);

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
                    // Recharger la conversation ou mettre à jour la carte
                    window.location.reload();
                }
            })
            .catch(function (err) {
                console.error("Erreur vote:", err);
            });
    };
})();
