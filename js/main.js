// Countdowns, local times, the photo gallery and live updates on an open lot page.

const serverTime = Date.parse(document.body.dataset.serverTime);
// Times come from the server, so a wrong device clock cannot change a countdown.
const clockOffset = Number.isNaN(serverTime) ? 0 : serverTime - Date.now();
const serverNow = () => Date.now() + clockOffset;

function timeLeft(closesAt) {
    const seconds = Math.floor((Date.parse(closesAt) - serverNow()) / 1000);
    if (seconds <= 0) return 'Closed';
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${minutes}m`;
    if (minutes > 0) return `${minutes}m ${seconds % 60}s`;
    return `${seconds}s`;
}

function tickCountdowns() {
    document.querySelectorAll('[data-closes]').forEach((node) => {
        node.textContent = timeLeft(node.dataset.closes);
    });
}
tickCountdowns();
setInterval(tickCountdowns, 1000);

document.querySelectorAll('[data-local-time]').forEach((node) => {
    node.textContent = new Date(node.dataset.localTime).toLocaleString();
});

// Filters on the market page apply as soon as they change.
document.querySelectorAll('form[data-autosubmit]').forEach((form) => {
    form.addEventListener('change', () => form.submit());
});

// Photo gallery
const galleryMain = document.querySelector('#gallery-main');
document.querySelectorAll('.thumb').forEach((thumb, _index, thumbs) => {
    thumb.addEventListener('click', () => {
        galleryMain.src = thumb.dataset.photo;
        thumbs.forEach((other) => other.setAttribute('aria-current', String(other === thumb)));
    });
});

// Live updates: checks for rival bids every 5 seconds, and at the moment the auction closes.
const auctionNode = document.querySelector('#auction');

if (auctionNode && auctionNode.dataset.open === '1') {
    const closesAt = document.querySelector('#time-left').dataset.closes;
    const pollUrl = document.querySelector('script[src*="js/main.js"]').src.replace(/js\/main\.js.*$/, 'actions/get_auction_action.php');

    const check = async () => {
        let next = Date.parse(closesAt) <= serverNow() ? 1000 : 5000;
        try {
            const response = await fetch(`${pollUrl}?id=${encodeURIComponent(auctionNode.dataset.id)}`, { credentials: 'same-origin' });
            if (response.ok) {
                const lot = await response.json();
                const changed = String(lot.bid_count) !== auctionNode.dataset.bids
                    || (lot.you_are_highest ? '1' : '0') !== auctionNode.dataset.highest;

                if (!lot.is_open) {
                    location.reload();
                    return;
                }
                if (changed) {
                    const input = document.querySelector('#bid-amount');
                    if (input && document.activeElement === input) {
                        // Do not reload while someone is typing a bid: just update the numbers.
                        input.min = lot.min_next_bid_input;
                        document.querySelector('#min-bid').textContent = lot.min_next_bid;
                        document.querySelector('#current-price').textContent = lot.current_price;
                    } else {
                        location.reload();
                        return;
                    }
                }
            }
        } catch {
            // A failed check just waits for the next one.
        }
        // Check again right when the auction closes, if that comes before the next check.
        const untilClose = Date.parse(closesAt) - serverNow();
        if (untilClose > 0 && untilClose < next) next = untilClose + 300;
        setTimeout(check, next);
    };
    setTimeout(check, 5000);
}

// Payout settings: show the network list for mobile money and the bank list for bank accounts.
// The hidden list is disabled so only one "provider" value is sent.
const payoutForm = document.querySelector('#payout-form');

if (payoutForm) {
    const numberLabel = payoutForm.querySelector('#number-label');
    const numberInput = payoutForm.querySelector('input[name="account_number"]');
    const showFields = () => {
        const method = payoutForm.querySelector('input[name="method"]:checked')?.value ?? 'mobile_money';
        payoutForm.querySelectorAll('[data-for]').forEach((field) => {
            const on = field.dataset.for === method;
            field.hidden = !on;
            field.querySelector('select').disabled = !on;
        });
        numberLabel.textContent = method === 'bank' ? 'Account number' : 'Mobile money number';
        numberInput.placeholder = method === 'bank' ? '' : '0241234567';
    };
    payoutForm.querySelectorAll('input[name="method"]').forEach((radio) => radio.addEventListener('change', showFields));
}
