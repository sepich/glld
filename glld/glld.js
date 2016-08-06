jQuery(document).ready(function($) {
    //Y axis type for min/max
    function axischange(o, t, v) {
      if(o.val()=='0') t.val(v);
      $('form[name="glldForm"]').submit();
    }
    $('#ymin_type').change(function(){
      axischange($(this), $('#yaxismin'), 0)
    });
    $('#ymax_type').change(function(){
      axischange($(this), $('#yaxismax'), 100)
    });

    //open popup
    $('#add_protoitem').click(function(event) {
      var
        templateid=$('input[name=templateid]').val(),
        popup = window.open('glld.php?popup='+templateid, 'zbx_popup', 'width=1024, height=768, top=50, left=100, resizable=yes, scrollbars=yes, location=no, menubar=no');
      popup.focus();
    });

    //add row to items table
    function addItem(tbl, graphtype, data={}) {
      var row=$('<tr>');

      data = $.extend({'calc_fnc':2, 'drawtype':0, 'yaxisside':0, 'type':0}, data); //defaults
      row.append( $('<td>').text(data['name']) );
      if(graphtype>=2){
        row.append( $('<td>')
          .append( $('<select name="items['+data['id']+'][type]">').append([
            $('<option>').val(0).text('Simple'),
            $('<option>').val(2).text('Graph sum')
          ]).val(data['type'])
        ))
      }
      row.append( $('<td>')
        .append( $('<select name="items['+data['id']+'][calc_fnc]">').append([
          $('<option>').val(7).text('all'),
          $('<option>').val(1).text('min'),
          $('<option>').val(2).text('avg').attr('selected','selected'),
          $('<option>').val(4).text('max')
        ]).val(data['calc_fnc'])
      ));
      if (graphtype==0) {
        row.append( $('<td>')
          .append( $('<select name="items['+data['id']+'][drawtype]">').append([
            $('<option>').val(0).text('Line'),
            $('<option>').val(1).text('Filled region'),
            $('<option>').val(2).text('Bold line'),
            $('<option>').val(3).text('Dot'),
            $('<option>').val(4).text('Dashed line'),
            $('<option>').val(5).text('Gradient line')
          ]).val(data['drawtype'])
        ))
      }
      if(graphtype<2){
        row.append( $('<td>')
          .append( $('<select name="items['+data['id']+'][yaxisside]">').append([
            $('<option>').val(0).text('Left'),
            $('<option>').val(1).text('Right')
          ]).val(data['yaxisside'])
        ))
      }
      row.append( $('<td>').append(
        $('<a href="#">').text('Remove').click(function(event) {
          $(this).closest('tr').remove();
          return false;
        })
      ));

      tbl.find('#itemButtonsRow').parent('tr').before(row);
    }
    //restore items
    $('input[type=hidden][name="item[][id]"]').each(function(index, el) {
      addItem($('#itemsTable'), $('#graphtype').val(), {
        'id': $(el).val(),
        'name': $(el).data('name'),
        'calc_fnc': $(el).data('calc'),
        'drawtype': $(el).data('drawtype'),
        'yaxisside': $(el).data('yaxisside'),
        'type': $(el).data('type')
      });
      $(el).remove();
    });
    //popup - singe item choosen
    $('.item_name').click(function(event) {
      var body = $(window.opener.document.body),
          tbl = body.find('#itemsTable'),
          graphtype = body.find('#graphtype').val();

      addItem(tbl, graphtype, {'name': $(this).text(), 'id': $(this).data('id')});
      window.close();
    });
    //popup - multiple selections
    $('#mselect').click(function(event) {
      var body = $(window.opener.document.body),
          tbl = body.find('#itemsTable'),
          graphtype = body.find('#graphtype').val();

      $('.row-selected a').each(function(index, el) {
        addItem(tbl, graphtype, {'name': $(el).text(), 'id': $(el).data('id')});
      });
      window.close();
    });
});