<x-layout.app title="SLA settings">
    <x-page-header title="SLA settings" :breadcrumbs="['Administration' => null, 'SLA settings' => null]" />

    <form method="POST" action="{{ route('admin.sla.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-6">
                <x-card title="Resolution time by priority">
                    <p class="text-muted">
                        How many hours an agent has to resolve a ticket. A ticket that is still unresolved after this time
                        is marked overdue and escalated.
                    </p>

                    @foreach ($priorities as $priority)
                        <x-form.input
                            :name="$priority->value.'_hours'"
                            :label="$priority->label().' priority, in hours'"
                            type="number"
                            :value="$hours[$priority->value] ?? null"
                            min="1"
                            max="720"
                            step="1"
                            inputmode="numeric"
                            :hint="$priority->description()"
                            :required="true" />
                    @endforeach

                    <div class="alert alert-secondary mb-0" role="note">
                        New times apply to tickets reported <strong>after you save</strong>. Tickets that are already
                        reported keep the due time they were given.
                    </div>
                </x-card>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <x-button type="submit" loading-text="Saving…">Save resolution times</x-button>
            <x-button variant="secondary" :href="route('dashboard')">Cancel</x-button>
        </div>
    </form>
</x-layout.app>
