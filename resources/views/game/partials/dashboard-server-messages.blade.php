@if ($dashboardServerMessages !== [])
    <div class="dashboard-server-message-stack" aria-live="polite" aria-atomic="true">
        @foreach ($dashboardServerMessages as $dashboardServerMessage)
            <section class="theme-status-flash dashboard-server-message-card theme-status-{{ $dashboardServerMessage['tone'] }}" data-dismiss-on-click>
                <button
                    type="button"
                    class="dashboard-server-message-close"
                    data-dismiss
                    aria-label="{{ __('Dismiss message') }}"
                >
                    <span aria-hidden="true">&times;</span>
                </button>

                @if (! empty($dashboardServerMessage['title']))
                    <p class="dashboard-server-message-kicker">{{ $dashboardServerMessage['title'] }}</p>
                @endif

                <div class="dashboard-server-message-lines">
                    @foreach ($dashboardServerMessage['lines'] as $line)
                        <p>{{ $line }}</p>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
