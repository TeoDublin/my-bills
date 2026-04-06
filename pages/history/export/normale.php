<?php

require_once __DIR__ . '/../functions.php';

return [
    'title' => 'BILLS',
    'create_payload_from_request' => static function (array $input, array $files = []): array {

        return bills_create_export_payload_from_request($input, $files);
    },
    'run' => static function (array $payload, array $job = []): void {

        $columns = [
            'ID' => 'id',
            'GROUP' => 'group_name',
            'NAME' => 'name',
            'VALUE' => 'value',
            'DATE' => 'date',
        ];

        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : bills_blank_filters();
        $options = bills_export_query_options($payload);
        $total_rows = bills_count_rows($filters, $options);
        $progress_bar_1 = Async()->progressbar('BILLS', $total_rows);
        $download_path = $progress_bar_1->download(bills_async_download_name('normale', 'xlsx'), 'xlsx');
        $sheet_temp_path = root('storage/async/tmp/' . date('Y-m-d') . '/' . bin2hex(random_bytes(16)) . '-sheet.xml');

        if ($total_rows === 0) {

            $progress_bar_1->warning('Nessuna riga trovata per i filtri selezionati.');
        }

        SimpleXlsx()->start_writer($sheet_temp_path);

        $row_number = SimpleXlsx()->append_rows($sheet_temp_path, [array_keys($columns)], 1);
        $offset = 0;
        $processed = 0;
        $chunk_size = 250;

        while (true) {

            $rows = bills_fetch_rows($filters, $options, $chunk_size, $offset);

            if ($rows === []) {

                break;
            }

            $export_rows = [];

            foreach ($rows as $row) {

                $export_rows[] = [
                    string_value($row['id'] ?? ''),
                    string_value($row['group_name'] ?? ''),
                    string_value($row['name'] ?? ''),
                    string_value($row['value'] ?? ''),
                    string_value($row['date'] ?? ''),
                ];
            }

            $row_number = SimpleXlsx()->append_rows($sheet_temp_path, $export_rows, $row_number);
            $processed += count($rows);
            $offset += count($rows);
            $progress_bar_1->refresh($processed);
        }

        SimpleXlsx()->finalize_writer($sheet_temp_path, $download_path);
        @unlink($sheet_temp_path);

        $progress_bar_1->refresh(max($processed, $total_rows));
    },
];
