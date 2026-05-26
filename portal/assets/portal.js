// ── ROLE SWITCHING ──
function switchRole(role) {
  // Update tabs
  document.querySelectorAll('.role-tab').forEach((t,i) => {
    t.classList.toggle('active', ['customer','advisor','sales'][i] === role);
  });

  // Show/hide sidebars
  document.querySelectorAll('.sidebar').forEach(s => s.style.display = 'none');
  document.getElementById('sidebar-' + role).style.display = 'flex';

  // Hide all views
  document.querySelectorAll('.view').forEach(v => {
    v.style.display = 'none';
    v.classList.remove('active');
  });

  // Show first view of that role
  const map = { customer: 'c-overview', advisor: 'a-overview', sales: 's-leads' };
  const view = document.getElementById(map[role]);
  view.style.display = 'block';
  view.classList.add('active');

  // Reset nav items
  document.querySelectorAll('#sidebar-' + role + ' .nav-item').forEach((item, i) => {
    item.classList.toggle('active', i === 0);
  });

  // Update avatar/name
  const names = { customer: 'Marcus Chen', advisor: 'Sarah Ramos', sales: 'James Diaz' };
  document.querySelector('.top-right span').textContent = names[role];
  const initials = { customer: 'MC', advisor: 'SR', sales: 'JD' };
  document.querySelector('.avatar').textContent = initials[role];
}

// ── VIEW SWITCHING ──
function showView(viewId, navEl) {
  // Get the role
  const role = document.querySelector('.role-tab.active').textContent.includes('Customer') ? 'customer' :
               document.querySelector('.role-tab.active').textContent.includes('Advisor') ? 'advisor' : 'sales';

  // Update nav
  document.querySelectorAll('#sidebar-' + role + ' .nav-item').forEach(n => n.classList.remove('active'));
  if (navEl) navEl.classList.add('active');

  // Hide all views
  document.querySelectorAll('.view').forEach(v => {
    v.style.display = 'none';
    v.classList.remove('active');
  });

  // Show target
  const view = document.getElementById(viewId);
  if (view) {
    view.style.display = 'block';
    view.classList.add('active');

    // Animate
    view.style.opacity = '0';
    view.style.transform = 'translateY(12px)';
    requestAnimationFrame(() => {
      view.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
      view.style.opacity = '1';
      view.style.transform = 'translateY(0)';
    });
  }
}

// Initial state
document.querySelector('#c-overview').style.display = 'block';


// WordPress AJAX integration
jQuery(document).ready(function($) {

    // Send message via AJAX
    $(document).on('click', '.six-send-btn', function() {
        var msg = $('.msg-input').val().trim();
        var receiver = $(this).data('receiver');
        if (!msg) return;

        $.post(sixPortal.ajax_url, {
            action: 'six_send_message',
            nonce: sixPortal.nonce,
            receiver_id: receiver,
            message: msg
        }, function(res) {
            if (res.success) {
                $('.msg-input').val('');
                // Append new message bubble
                appendMessage(res.data);
            }
        });
    });

    // Book meeting
    $(document).on('click', '.book-slot-btn', function() {
        var start = $(this).data('start');
        $.post(sixPortal.ajax_url, {
            action: 'six_book_meeting',
            nonce: sixPortal.nonce,
            start: start,
            duration: 30
        }, function(res) {
            if (res.success) {
                alert('Meeting booked! Google Meet link: ' + res.data.meet_link);
            }
        });
    });

    // Request service
    $(document).on('click', '.request-service-btn', function() {
        var service = $(this).data('service');
        $.post(sixPortal.ajax_url, {
            action: 'six_request_service',
            nonce: sixPortal.nonce,
            service: service
        }, function(res) {
            if (res.success) {
                location.reload();
            }
        });
    });

    // Approve service (advisor)
    $(document).on('click', '.approve-service-btn', function() {
        var serviceId = $(this).data('service-id');
        $.post(sixPortal.ajax_url, {
            action: 'six_approve_service',
            nonce: sixPortal.nonce,
            service_id: serviceId
        }, function(res) {
            if (res.success) location.reload();
        });
    });

});
