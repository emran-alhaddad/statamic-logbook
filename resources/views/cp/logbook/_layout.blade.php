@extends('statamic::layout')

@section('title', 'Logbook')

@section('content')
<style>
  /* Full width in CP */
  .content .container {
    max-width: 100% !important;
  }

  /* Small UI tweaks */
  .logbook-subtle {
    color: rgba(55, 65, 81, .85);
  }

  .dark .logbook-subtle {
    color: rgba(221, 225, 229, .7);
  }

  .logbook-card {
    border-radius: 14px;
  }

  .logbook-tab {
    position: relative;
    color: #73808c;
    text-decoration: none;
  }

  .logbook-tab.active {
    color: #43a9ff;
    font-weight: 600;
  }

  .logbook-tab.active::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    bottom: -1px;
    height: 2px;
    background: #43a9ff;
    border-radius: 999px;
  }

  /* Modal */
  .logbook-modal-backdrop {
    background-color: rgba(0, 0, 0, .55);
  }

  .logbook-modal-pre {
    background: #0b1220;
    color: #e5e7eb;
    border-radius: 12px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
    box-shadow: 0 10px 25px rgba(0, 0, 0, .3);
  }
</style>

<div class="mb-5">
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="mb-1 text-2xl font-semibold">Logbook</h1>
      <div class="text-sm logbook-subtle">
        System logs & user audit logs
      </div>
    </div>
    <div class="flex items-center gap-2">
      <button
        class="btn"
        type="button"
        data-command-key="prune"
        data-command-url="{{ cp_route('utilities.logbook.actions.prune') }}"
        data-command-label="Prune"
        onclick="__logbookRunCommand(event)">
        Prune Logs
      </button>
      <button
        class="btn"
        type="button"
        data-command-key="flush-spool"
        data-command-url="{{ cp_route('utilities.logbook.actions.flush-spool') }}"
        data-command-label="Flush Spool"
        onclick="__logbookRunCommand(event)">
        Flush Spool
      </button>
    </div>
  </div>
</div>

<div class="card p-0 overflow-hidden w-full logbook-card">
  <div class="flex items-center gap-6 border-b px-4 bg-gray-50/60">
    <a href="{{ cp_route('utilities.logbook.system') }}"
      class="py-3 text-sm font-medium logbook-tab {{ $active === 'system' ? 'active' : '' }}">
      System Logs
    </a>

    <a href="{{ cp_route('utilities.logbook.audit') }}"
      class="py-3 text-sm font-medium logbook-tab {{ $active === 'audit' ? 'active' : '' }}">
      Audit Logs
    </a>
  </div>

  <div class="p-4 w-full emran" v-pre>
    @yield('panel')
  </div>
</div>

{{-- Modal --}}
<div id="logbook-modal" class="hidden fixed inset-0 z-[9999] w-screen h-screen overflow-auto">
  <div class="absolute inset-0 logbook-modal-backdrop" style="backdrop-filter: blur(6px);" onclick="__logbookClose()"></div>
  <div class="relative min-h-screen w-full flex items-center justify-center py-10 px-2">
    <div class="card w-full max-w-5xl p-0 logbook-card"
      style="max-height:90vh; display: flex; flex-direction: column; border: 1px solid #2870F5; box-shadow: 0 20px 45px rgba(0,0,0,.3);">
      <div class="flex justify-between items-center border-b px-4 py-3 bg-gray-50/60 sticky top-0 z-10">
        <div class="min-w-0">
          <div id="logbook-modal-title" class="font-semibold text-sm truncate">Details</div>
          <div id="logbook-modal-subtitle" class="text-xs text-gray-600 truncate"></div>
        </div>
        <div class="flex items-center gap-2">
          <button class="btn" onclick="__logbookCopy(event)">Copy</button>
          <button class="btn" onclick="__logbookClose()">Close</button>
        </div>
      </div>
      <pre id="logbook-modal-body"
        class="p-4 text-xs overflow-auto flex-1 logbook-modal-pre"
        style="max-height: 70vh;"></pre>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
  const __logbookCommandState = {
    running: {}
  };

  function __logbookToast(type, text) {
    const fallback = function() {
      if (type === 'error') {
        alert(text);
      } else {
        console.log(text);
      }
    };

    if (window.Statamic && typeof window.Statamic.$toast === 'object') {
      const toast = window.Statamic.$toast;
      if (type === 'success' && typeof toast.success === 'function') return toast.success(text);
      if (type === 'error' && typeof toast.error === 'function') return toast.error(text);
      if (typeof toast.info === 'function') return toast.info(text);
      if (typeof toast.show === 'function') return toast.show(text);
    }

    if (window.$toast && typeof window.$toast === 'object') {
      const toast = window.$toast;
      if (type === 'success' && typeof toast.success === 'function') return toast.success(text);
      if (type === 'error' && typeof toast.error === 'function') return toast.error(text);
      if (typeof toast.info === 'function') return toast.info(text);
    }

    fallback();
  }

  async function __logbookRunCommand(event) {
    const button = event.currentTarget;
    const url = button.dataset.commandUrl;
    const commandKey = button.dataset.commandKey || '';
    const commandLabel = button.dataset.commandLabel || 'Command';
    const originalText = button.textContent;

    if (!url || __logbookCommandState.running[commandKey]) {
      return;
    }

    __logbookCommandState.running[commandKey] = true;
    button.disabled = true;
    button.textContent = `${commandLabel}...`;
    __logbookToast('info', `${commandLabel}: in-progress`);

    try {
      const formData = new URLSearchParams();
      formData.append('_token', '{{ csrf_token() }}');

      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData.toString(),
      });

      const data = await response.json().catch(function() {
        return {};
      });
      if (response.ok && data.ok) {
        __logbookToast('success', `${commandLabel}: done`);
      } else {
        const message = data && data.message ? data.message : 'Unknown error';
        __logbookToast('error', `${commandLabel}: failed (${message})`);
      }
    } catch (error) {
      __logbookToast('error', `${commandLabel}: failed (${error && error.message ? error.message : 'Request error'})`);
    } finally {
      __logbookCommandState.running[commandKey] = false;
      button.disabled = false;
      button.textContent = originalText;
    }
  }

  // Unicode-safe base64 decode
  function __logbookDecodeBase64Unicode(b64) {
    if (!b64) return '';
    const binary = atob(b64);
    const bytes = Uint8Array.from(binary, c => c.charCodeAt(0));
    return new TextDecoder('utf-8').decode(bytes);
  }

  function __logbookOpenModal(title, payloadB64, subtitle) {
    document.getElementById('logbook-modal-title').textContent = title || 'Details';
    document.getElementById('logbook-modal-subtitle').textContent = subtitle || '';
    const text = payloadB64 ? __logbookDecodeBase64Unicode(payloadB64) : '—';
    document.getElementById('logbook-modal-body').textContent = text;
    document.getElementById('logbook-modal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }

  function __logbookClose() {
    document.getElementById('logbook-modal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }

  function __logbookCopy() {
    const text = document.getElementById('logbook-modal-body').textContent || '';
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function() {
        alert('Copied to clipboard');
      }, function(err) {
        // fallback
        __fallbackCopy(text);
      });
    } else {
      __fallbackCopy(text);
    }

    function __fallbackCopy(txt) {
      const textarea = document.createElement('textarea');
      textarea.value = txt;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'absolute';
      textarea.style.left = '-9999px';
      document.body.appendChild(textarea);
      textarea.select();
      try {
        document.execCommand('copy');
        alert('Copied to clipboard');
      } catch (e) {
        alert('Failed to copy');
      }
      document.body.removeChild(textarea);
    }
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') __logbookClose();
  });
</script>
@endsection