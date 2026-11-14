<x-error-page
    code="500"
    title="Something went wrong on our side"
    text="We could not finish that. Try again in a moment. If it keeps happening, tell the ICT help desk what you were doing when it happened.">
    <x-button :href="url()->previous(url('/'))">Try again</x-button>
</x-error-page>
