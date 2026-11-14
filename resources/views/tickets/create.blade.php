<x-layout.app title="Report a problem">
    <x-page-header title="Report a problem" :breadcrumbs="['My tickets' => route('tickets.index'), 'Report a problem' => null]" />

    <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="row g-3">
            <div class="col-lg-8">
                <x-card title="What is the problem?">
                    <x-form.input name="title" label="Title" hint="A short summary, 5 to 150 characters." maxlength="150" autocomplete="off" autofocus :required="true" />

                    <x-form.select
                        name="category_id"
                        label="Category"
                        :options="$categories->pluck('name', 'id')->all()"
                        placeholder="Choose a category"
                        hint="Not sure? The guide on the right says what each one covers."
                        :required="true" />

                    <x-form.radios
                        name="priority"
                        label="How urgent is it?"
                        value="medium"
                        :required="true"
                        :options="collect($priorities)->mapWithKeys(fn ($priority) => [
                            $priority->value => [
                                'label' => $priority->label(),
                                'description' => $priority->description().(isset($hours[$priority->value]) ? ' We aim to resolve it within '.$hours[$priority->value].' '.Str::plural('hour', $hours[$priority->value]).'.' : ''),
                            ],
                        ])->all()" />

                    <x-form.textarea name="description" label="Description" rows="6" :maxlength="5000" hint="What happened, and what did you expect to happen? Include any error message." :required="true" />

                    <x-form.file name="attachment" label="Attachment" accept=".jpg,.jpeg,.png,.pdf" hint="Optional. One file: JPG, PNG or PDF, up to 5 MB. A screenshot helps." />
                </x-card>
            </div>

            <div class="col-lg-4">
                <x-card title="Which category?">
                    <ul class="category-guide">
                        @foreach ($categories as $category)
                            <li>
                                <strong>{{ $category->name }}</strong>
                                @if ($category->description)
                                    <span>{{ $category->description }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <x-button type="submit" loading-text="Submitting…">Submit ticket</x-button>
            <x-button variant="secondary" :href="route('tickets.index')">Cancel</x-button>
        </div>
    </form>
</x-layout.app>
