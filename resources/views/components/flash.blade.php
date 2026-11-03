{{-- Shows the toast queued by WebController::flash() on the next page. Renders nothing when there is none. --}}
@php($toast = session(\App\Http\Controllers\Web\WebController::FLASH_KEY))
@if ($toast)
    {{-- also readable by the in-place refresh scripts, which show the toast themselves --}}
    <div id="flash-data" hidden data-type="{{ $toast['type'] }}" data-message="{{ $toast['message'] }}"></div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (typeof toast === "function") toast(@json($toast['message'])@if ($toast['type'] === 'error'), "error"@endif);
        });
    </script>
@endif
