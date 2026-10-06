{{--
    Fixed print footer line.

    Params:
      $text  string  footer text; defaults to the FinancePro/VICOBA signature line
--}}
<div class="print-doc-footer">
    {{ $text ?? (config('app.name', 'FinancePro') . ' · VICOBA System · Printed ' . now()->format('d M Y, H:i')) }}
</div>
