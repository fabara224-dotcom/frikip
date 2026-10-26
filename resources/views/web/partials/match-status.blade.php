@if ($m['status'] === 'live')
<span class="live">{{ ! empty($m['period']) ? mb_strtoupper($m['period']) : 'EN DIRECT · '.$m['minute']."'" }}</span>
@elseif ($m['status'] === 'finished')
<span>{{ preg_match('/^(Annulé|Arrêté|Reporté)/u', (string) $m['date_label']) ? $m['date_label'] : 'Terminé · '.($m['date_label'] ?: "Aujourd'hui") }}</span>
@else
<span>{{ $m['date_label'] }}</span>
@endif
