(function(){
	'use strict';
	function q(s,c){return(c||document).querySelector(s);} function qa(s,c){return Array.prototype.slice.call((c||document).querySelectorAll(s));}
	function ajax(action,data){ var b=new FormData(); b.append('action',action); b.append('nonce',(window.CASAdmin||{}).nonce||''); Object.keys(data||{}).forEach(function(k){b.append(k,data[k]);}); return fetch((window.CASAdmin||{}).ajaxUrl,{method:'POST',credentials:'same-origin',body:b}).then(function(r){return r.json();}); }
	function setText(el,text){ if(el){el.textContent=text||'';} }
	function closestForm(el){ return el.closest('form') || document; }
	document.addEventListener('DOMContentLoaded',function(){
		var list=q('#cas-holiday-list'),add=q('.cas-add-holiday'); if(list&&add){ add.addEventListener('click',function(){ var p=document.createElement('p'); p.innerHTML='<input type="date" name="holidays[]"> <button type="button" class="button-link cas-remove-holiday">Remove</button>'; list.appendChild(p); }); list.addEventListener('click',function(e){ if(e.target.classList.contains('cas-remove-holiday')){e.preventDefault();e.target.parentNode.remove();} }); }
		qa('a[href*="cas_appointment_action"],a[href*="cas_cancel_waiting"],a[href*="cas_deactivate_doctor"],a[href*="cas_deactivate_patient"],a[href*="cas_deactivate_family_member"]').forEach(function(a){ a.addEventListener('click',function(e){ if(!confirm((window.CASAdmin&&CASAdmin.i18n&&CASAdmin.i18n.confirmAction)||'Are you sure?')){e.preventDefault();} }); });
		qa('form input[name="action"][value="cas_test_sms"]').forEach(function(input){ var form=input.closest('form'); form&&form.addEventListener('submit',function(e){ e.preventDefault(); ajax('cas_admin_test_sms',{mobile:q('[name="test_mobile"]',form).value,message:q('[name="test_message"]',form).value}).then(function(j){ alert(j&&j.success?'Test SMS sent':(j&&j.data&&j.data.message)||'Failed'); }); }); });
		qa('[data-cas-check-balance]').forEach(function(button){ button.addEventListener('click',function(e){ e.preventDefault(); var form=button.closest('form')||document, urlInput=q('[name="sms_balance_url"]',form), result=q('[data-cas-balance-result]',form), spinner=q('[data-cas-balance-spinner]',form); button.disabled=true; if(spinner){spinner.classList.add('is-active');} setText(result,'Checking SMS balance...'); ajax('cas_admin_check_balance',{balance_url:urlInput?urlInput.value:''}).then(function(j){ setText(result,(j&&j.data&&j.data.message)?j.data.message:(j&&j.success?'Balance checked.':'Could not check balance.')); if(result){result.className='cas-balance-result '+(j&&j.success?'notice-success':'notice-error');} }).catch(function(){ setText(result,'Could not check SMS balance. Please verify the API URL and that your server can reach BulkSMSBD.'); if(result){result.className='cas-balance-result notice-error';} }).finally(function(){ button.disabled=false; if(spinner){spinner.classList.remove('is-active');} }); }); });
		qa('form input[name="action"][value="cas_promote_waiting"]').forEach(function(input){ var form=input.closest('form'); form&&form.addEventListener('submit',function(e){ if(!confirm('Promote this patient?')){e.preventDefault();return;} if(form.classList.contains('cas-inline-form')){ e.preventDefault(); ajax('cas_admin_promote_waiting',{waiting_id:q('[name="waiting_id"]',form).value,serial_number:q('[name="serial_number"]',form).value,appointment_date:q('[name="appointment_date"]',form)?q('[name="appointment_date"]',form).value:''}).then(function(j){ alert(j&&j.success?'Promoted':(j&&j.data&&j.data.message)||'Failed'); if(j&&j.success){location.reload();} }); } }); });
		qa('a[href*="cas_print_appointments"]').forEach(function(a){ a.addEventListener('click',function(e){e.preventDefault();window.open(a.href,'_blank','noopener');}); });

		/* Manual booking patient tabs: only the active panel contributes required fields. */
		qa('[data-cas-patient-tabs]').forEach(function(tabs){
			var form=tabs.closest('form'), mode=q('[data-cas-patient-mode]',form);
			function activate(name){
				qa('[data-cas-patient-tab]',tabs).forEach(function(button){ var active=button.getAttribute('data-cas-patient-tab')===name; button.classList.toggle('nav-tab-active',active); button.setAttribute('aria-selected',active?'true':'false'); });
				qa('[data-cas-patient-panel]',tabs).forEach(function(panel){ var active=panel.getAttribute('data-cas-patient-panel')===name; panel.hidden=!active; qa('input,select,textarea,button',panel).forEach(function(field){ if(field.type!=='submit'){field.disabled=!active;} }); });
				if(mode){mode.value=name;}
			}
			qa('[data-cas-patient-tab]',tabs).forEach(function(button){ button.addEventListener('click',function(){activate(button.getAttribute('data-cas-patient-tab'));}); });
			activate(mode&&mode.value?mode.value:'existing');
		});


		/*
		 * Manual appointment validation.
		 *
		 * Validate all required values before the form is submitted, show one clear
		 * popup message, and focus the field that needs correction. The selected
		 * doctor's schedule is also checked through the existing slot-map endpoint,
		 * so inactive weekdays and holidays are reported before a full page reload.
		 * Server-side validation remains authoritative and is not replaced by this.
		 */
		qa('[data-cas-admin-appointment-form]').forEach(function(form){
			var isSubmitting = false;
			var i18n = (window.CASAdmin && CASAdmin.i18n) || {};
			function fail(message, field){
				alert(message);
				if(field){
					try { field.focus({preventScroll:false}); } catch(e) { field.focus(); }
					if(field.scrollIntoView){ field.scrollIntoView({behavior:'smooth',block:'center'}); }
				}
				return false;
			}
			function activePatientMode(){
				var mode=q('[data-cas-patient-mode]',form);
				return mode && mode.value === 'new' ? 'new' : 'existing';
			}
			function validBangladeshMobile(value){
				var clean=String(value||'').replace(/[\s\-()]/g,'');
				return /^(?:01\d{9}|(?:\+?880)1\d{9})$/.test(clean);
			}
			function validateLocal(){
				var doctor=q('[data-cas-admin-doctor]',form);
				var date=q('[data-cas-admin-date]',form);
				var serial=q('[data-cas-admin-serial]',form);
				var vip=q('[data-cas-vip-toggle]',form);
				var vipTime=q('[name="vip_reporting_time"]',form);
				if(!doctor || !doctor.value){ return fail(i18n.selectDoctor||'Please select a doctor.',doctor); }
				if(!date || !date.value){ return fail(i18n.selectDate||'Please select an appointment date.',date); }
				if(vip && vip.checked){
					if(!vipTime || !vipTime.value){ return fail(i18n.vipTimeRequired||'Please enter the VIP reporting time.',vipTime); }
				} else if(!serial || !serial.value || parseInt(serial.value,10)<1){
					return fail(i18n.selectSerial||'Please select a serial number.',serial);
				}
				if(activePatientMode()==='existing'){
					var patient=q('[data-cas-existing-patient]',form);
					if(!patient || !patient.value || patient.value==='0'){ return fail(i18n.selectPatient||'Please select an existing patient.',patient); }
				} else {
					var name=q('[data-cas-new-name]',form);
					var mobile=q('[data-cas-new-mobile]',form);
					var age=q('[data-cas-new-age]',form);
					var email=q('[data-cas-new-email]',form);
					if(!name || !name.value.trim()){ return fail(i18n.newPatientName||'Please enter the new patient name.',name); }
					if(!mobile || !validBangladeshMobile(mobile.value)){ return fail(i18n.newPatientMobile||'Please enter a valid Bangladeshi mobile number.',mobile); }
					if(age && age.value!=='' && (parseInt(age.value,10)<0 || parseInt(age.value,10)>125)){ return fail(i18n.invalidAge||'Patient age must be between 0 and 125.',age); }
					if(email && email.value && !email.checkValidity()){ return fail(i18n.invalidEmail||'Please enter a valid email address.',email); }
				}
				return true;
			}
			form.addEventListener('submit',function(e){
				if(isSubmitting){ return; }
				e.preventDefault();
				if(!validateLocal()){ return; }
				var doctor=q('[data-cas-admin-doctor]',form);
				var date=q('[data-cas-admin-date]',form);
				var submitter=e.submitter || q('button[type="submit"],input[type="submit"]',form);
				var originalText='';
				if(submitter){
					originalText=submitter.tagName==='INPUT'?submitter.value:submitter.textContent;
					submitter.disabled=true;
					if(submitter.tagName==='INPUT'){submitter.value=i18n.checkingBooking||'Checking appointment details...';}
					else{submitter.textContent=i18n.checkingBooking||'Checking appointment details...';}
				}
				ajax('cas_admin_get_slot_map',{doctor_id:doctor.value,appointment_date:date.value}).then(function(j){
					if(!j || !j.success){
						return fail((j&&j.data&&j.data.message)||i18n.validationFailed||'Could not validate the appointment. Please try again.',date);
					}
					isSubmitting=true;
					form.submit();
				}).catch(function(){
					fail(i18n.validationFailed||'Could not validate the appointment. Please try again.',date);
				}).finally(function(){
					if(!isSubmitting && submitter){
						submitter.disabled=false;
						if(submitter.tagName==='INPUT'){submitter.value=originalText;}else{submitter.textContent=originalText;}
					}
				});
			});
		});

		/* Date-filtered appointment worklists are operational call lists. Keep every row expanded. */
		qa('.cas-daily-worklist .wp-list-table tbody tr').forEach(function(row){
			if (row.classList.contains('no-items')) { return; }
			row.classList.add('is-expanded');
			var toggle = q('.toggle-row', row);
			if (toggle) { toggle.setAttribute('aria-expanded', 'true'); toggle.setAttribute('tabindex', '-1'); }
		});

		var activeContext=null;
		function openModal(context){ var modal=q('[data-cas-slot-modal]'); if(!modal){return;} activeContext=context; modal.hidden=false; var d=q('[data-cas-slot-date]',modal); if(d){d.value=context.date||'';} loadSlots(); }
		function closeModal(){ var modal=q('[data-cas-slot-modal]'); if(modal){modal.hidden=true;} activeContext=null; }
		function renderSlots(slots){ var modal=q('[data-cas-slot-modal]'), grid=q('[data-cas-slot-grid]',modal); if(!grid){return;} grid.innerHTML=''; if(!slots||!slots.length){grid.innerHTML='<p>No slots found.</p>';return;} slots.forEach(function(slot){ var btn=document.createElement('button'); btn.type='button'; btn.className='cas-slot-tile '+(slot.is_booked?'is-booked':'is-free'); btn.disabled=!!slot.is_booked; btn.innerHTML='<strong>#'+slot.serial+'</strong><span>'+slot.reporting_time_display+'</span>'+(slot.is_booked?'<em>'+((slot.patient_name||'Booked')+' '+(slot.status?'('+slot.status+')':''))+'</em>':'<em>Free</em>'); btn.addEventListener('click',function(){ if(!activeContext){return;} if(activeContext.serialInput){activeContext.serialInput.value=slot.serial;} if(activeContext.dateInput){activeContext.dateInput.value=q('[data-cas-slot-date]',modal).value;} closeModal(); }); grid.appendChild(btn); }); }
		function loadSlots(){ if(!activeContext){return;} var modal=q('[data-cas-slot-modal]'), d=q('[data-cas-slot-date]',modal), grid=q('[data-cas-slot-grid]',modal); if(grid){grid.innerHTML='<p>Loading slots...</p>';} ajax('cas_admin_get_slot_map',{doctor_id:activeContext.doctorId,appointment_date:d?d.value:activeContext.date}).then(function(j){ if(j&&j.success){renderSlots(j.data.slots||[]);} else if(grid){grid.innerHTML='<p class="cas-error">'+((j&&j.data&&j.data.message)||'Could not load slots.')+'</p>';} }).catch(function(){ if(grid){grid.innerHTML='<p class="cas-error">Could not load slots.</p>';} }); }
		qa('.cas-open-slot-picker').forEach(function(button){ button.addEventListener('click',function(){ var form=closestForm(button), doctorEl=q('[data-cas-admin-doctor]',form), dateEl=q('[data-cas-admin-date]',form), serialEl=q('[data-cas-admin-serial]',form); var doctorId=doctorEl?doctorEl.value:''; var date=dateEl?dateEl.value:''; if(!doctorId||!date){ alert('Please select doctor and date first.'); return; } openModal({doctorId:doctorId,date:date,dateInput:dateEl,serialInput:serialEl}); }); });
		qa('[data-cas-slot-close]').forEach(function(b){ b.addEventListener('click',closeModal); }); qa('[data-cas-slot-refresh]').forEach(function(b){ b.addEventListener('click',loadSlots); });
	});
})();


/* VIP appointment mode: keep VIP bookings outside the normal serial queue. */
document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('[data-cas-vip-toggle]');
  var timeRow = document.querySelector('[data-cas-vip-time-row]');
  var serial = document.querySelector('[data-cas-admin-serial]');
  var picker = document.querySelector('.cas-open-slot-picker');
  if (!toggle || !timeRow || !serial) return;
  function syncVipMode() {
    var vip = toggle.checked;
    timeRow.hidden = !vip;
    serial.required = !vip;
    serial.disabled = vip;
    if (picker) picker.disabled = vip;
  }
  toggle.addEventListener('change', syncVipMode);
  syncVipMode();
});
