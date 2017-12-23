<tr
        data-file-id="{{ $data['id'] }}"
        data-file-parent-id="{{ $data['parent_id'] }}"
        data-file-type="{{ $data['type'] }}"
        data-file-name="{{ $data['name'] }}"
        data-file-size="{{ $data['size'] }}"
        data-file-updated-at="{{ $data['updated_at'] }}"
>
    <td><i class="fas fa-{{ ($data['type'] == 1)? 'file' : 'folder' }}" style="font-size: 25px"></i></td>
    <td class="file-name">{{ $data['name'] }}</td>
    <td class="file-size">{{ UnitConverter::bytesToHuman($data['size']) }}</td>
    <td class="file-updated-at">{{ $data['updated_at'] }}</td>
</tr>