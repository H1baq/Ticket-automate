<x-error-page
    code="419"
    title="This page has expired"
    text="For your security, a form stops working if it stays open for too long. Open the page again and resend it. You may need to retype what you entered.">
    <x-button :href="url()->previous(url('/'))">Open the page again</x-button>
</x-error-page>
