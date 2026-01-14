<script>
    // Afficher une notification de succès
    @if(session('success'))
        toastr.success("{{ session('success') }}");
    @endif

    // Afficher une notification d'erreur
    @if(session('error'))
        @php 

            $errorMessages = session('error');
            // explode errorMessages by <br>
            $errorMessages = explode('<br>', $errorMessages);
            
            if(is_array($errorMessages)) {
                
                // show each error in toastr.error
                foreach($errorMessages as $errorMessage) {
                    @endphp
                    toastr.error("{{ $errorMessage }}");
                    @php
                }

            }

        @endphp

    @endif

    // Afficher une notification d'avertissement (facultatif)
    @if(session('warning'))
        toastr.warning("{{ session('warning') }}");
    @endif

    // Afficher une notification d'information (facultatif)
    @if(session('info'))
        toastr.info("{{ session('info') }}");
    @endif

    // Afficher les erreurs de validation
    @if($errors->any())
        @foreach ($errors->all() as $error)
            toastr.error("{{ $error }}");
        @endforeach
    @endif
</script>
