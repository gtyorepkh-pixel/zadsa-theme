(function () {
 'use strict';
 document.addEventListener('click', function (event) {
  var add=event.target.closest('[data-add-row]'), row=event.target.closest('[data-row]'), action=event.target.closest('[data-row-action]');
  if(add){
   event.preventDefault();var group=add.closest('.zad-native-group'), rows=group.querySelector('[data-rows]'), template=group.querySelector('[data-row-template]');
   var index=Array.from(rows.children).reduce(function(max,item){return Math.max(max,Number(item.dataset.index)||0)},-1)+1;
   var holder=document.createElement('template');holder.innerHTML=template.innerHTML.replace(/__row__/g,String(index));rows.appendChild(holder.content.cloneNode(true));
   rows.lastElementChild.querySelectorAll('input,select,textarea').forEach(function(input){if(input.id){var previous=input.id;input.id+='-'+index;rows.lastElementChild.querySelectorAll('label').forEach(function(label){if(label.htmlFor===previous)label.htmlFor=input.id;});}});return;
  }
  if(action&&row){event.preventDefault();var rows=row.parentElement;if(action.dataset.rowAction==='delete')row.remove();else if(action.dataset.rowAction==='up'&&row.previousElementSibling)rows.insertBefore(row,row.previousElementSibling);else if(action.dataset.rowAction==='down'&&row.nextElementSibling)rows.insertBefore(row.nextElementSibling,row);return;}
  var order=event.target.closest('[data-select-order]');if(order){event.preventDefault();var select=order.closest('.zad-native-field').querySelector('select'), selected=Array.from(select.options).filter(function(item){return item.selected&&item.value;});if(order.dataset.selectOrder==='down')selected.reverse();selected.forEach(function(item){var sibling=order.dataset.selectOrder==='up'?item.previousElementSibling:item.nextElementSibling;if(sibling&&sibling.value&&!sibling.selected){if(order.dataset.selectOrder==='up')select.insertBefore(item,sibling);else select.insertBefore(sibling,item);}});return;}
  var pick=event.target.closest('[data-media-pick]');if(pick&&window.wp&&wp.media){
   event.preventDefault();var box=pick.closest('[data-native-media]'),frame=wp.media({title:'اختيار ملف',multiple:false});
   frame.on('select',function(){var item=frame.state().get('selection').first().toJSON();box.querySelector('[data-media-id]').value=item.id;box.querySelector('[data-media-url]').value=item.url;var preview=box.querySelector('[data-media-preview]');preview.replaceChildren();if(item.type==='image'){var img=document.createElement('img');img.src=item.sizes&&item.sizes.thumbnail?item.sizes.thumbnail.url:item.url;img.alt=item.alt||'';preview.appendChild(img);}});frame.open();
  }
 });
})();
