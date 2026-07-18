(function () {
	'use strict';
	var state = { loginMobile: '', countdownTimer: null, otpAbortController: null, otpAutoSubmitting: false, doctorId: '', doctorName: '', patientId: '', patientName: '', date: '', serial: '', timeRaw: '', timeLabel: '' };
	function ready(cb){ if(document.readyState !== 'loading'){ cb(); return; } document.addEventListener('DOMContentLoaded', cb); }
	function q(s,c){ return (c||document).querySelector(s); }
	function qa(s,c){ return Array.prototype.slice.call((c||document).querySelectorAll(s)); }
	function cfg(){ return window.CASPublic || { ajaxUrl:'', nonce:'', i18n:{} }; }
	function t(key,fallback){ return (cfg().i18n&&cfg().i18n[key]) || fallback; }
	function ajax(action,data){ var body=new FormData(); body.append('action',action); body.append('nonce',cfg().nonce||''); Object.keys(data||{}).forEach(function(k){body.append(k,data[k]);}); if(!cfg().ajaxUrl){return Promise.reject(new Error('AJAX endpoint is unavailable.'));} return fetch(cfg().ajaxUrl,{method:'POST',credentials:'same-origin',body:body}).then(function(r){ return r.text().then(function(raw){ try { return JSON.parse(raw); } catch(e){ var message=(raw||'').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim(); return {success:false,data:{message:message||'The server returned an invalid response. Please refresh and try again.'}}; } }); }); }
	function alertBox(w,msg,type){ var a=q('[data-cas-alert]',w); if(!a){return;} a.hidden=false; a.className='cas-alert cas-alert-'+(type||'info'); a.textContent=msg||''; }
	function clearAlert(w){ var a=q('[data-cas-alert]',w); if(a){ a.hidden=true; a.textContent=''; } }
	function loading(w,on){ var sp=q('[data-cas-spinner]',w); if(sp){ sp.hidden=!on; } qa('button',w).forEach(function(b){ if(b.classList.contains('cas-serial-tile') || b.hasAttribute('data-cas-resend-otp')){return;} b.disabled=!!on; }); }
	function setStep(w,group,name){ var sel=group==='login'?'[data-cas-login-step]':'[data-cas-booking-step]'; var attr=group==='login'?'data-cas-login-step':'data-cas-booking-step'; qa(sel,w).forEach(function(sec){ var active=sec.getAttribute(attr)===name; sec.hidden=!active; sec.classList.toggle('is-active',active); }); qa('[data-step-dot]',w).forEach(function(dot){ dot.classList.toggle('is-active',dot.getAttribute('data-step-dot')===name); }); }
	function dateLabel(s){ var p=String(s||'').split('-'), d; if(p.length!==3){return s;} d=new Date(Number(p[0]),Number(p[1])-1,Number(p[2])); return Number.isNaN(d.getTime())?s:d.toLocaleDateString(); }
	function serializeForm(form){ var data={}; new FormData(form).forEach(function(v,k){ data[k]=v; }); return data; }
	function escapeHTML(str){ return String(str||'').replace(/[&<>'"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c];}); }


	/** Stop any active WebOTP request when the user changes step or requests a new code. */
	function stopWebOtp(){
		if(state.otpAbortController && typeof state.otpAbortController.abort==='function'){
			state.otpAbortController.abort();
		}
		state.otpAbortController=null;
	}

	/**
	 * Ask a supported secure-context browser to retrieve the next domain-bound SMS
	 * credential. Unsupported browsers silently fall back to OS autofill/manual entry.
	 */
	function startWebOtp(wrapper,verifyForm){
		var input=q('[name="otp"]',verifyForm), length=parseInt(wrapper.getAttribute('data-cas-otp-length')||input&&input.maxLength||'6',10)||6;
		if(!input || !window.isSecureContext || !('OTPCredential' in window) || !navigator.credentials || typeof navigator.credentials.get!=='function'){
			return;
		}
		stopWebOtp();
		state.otpAbortController=typeof AbortController!=='undefined'?new AbortController():null;
		var options={otp:{transport:['sms']}};
		if(state.otpAbortController){options.signal=state.otpAbortController.signal;}
		navigator.credentials.get(options).then(function(credential){
			if(!credential || !credential.code){return;}
			var code=String(credential.code).replace(/\D/g,'').slice(0,length);
			if(code.length!==length){return;}
			input.value=code;
			input.dispatchEvent(new Event('input',{bubbles:true}));
		}).catch(function(error){
			/* Abort, timeout, denial, and unsupported states are normal fallbacks. */
			if(error && error.name!=='AbortError' && window.console && console.debug){console.debug('CAS WebOTP fallback:',error.name||error);}
		}).finally(function(){state.otpAbortController=null;});
	}

	/** Keep OTP input numeric and submit once exactly the configured digit count exists. */
	function initOtpInput(wrapper,verifyForm){
		var input=q('[name="otp"]',verifyForm), length=parseInt(wrapper.getAttribute('data-cas-otp-length')||input&&input.maxLength||'6',10)||6, submitTimer=null;
		if(!input){return;}
		function normalizeAndMaybeSubmit(){
			var clean=String(input.value||'').replace(/\D/g,'').slice(0,length);
			if(input.value!==clean){input.value=clean;}
			if(submitTimer){window.clearTimeout(submitTimer);submitTimer=null;}
			if(clean.length===length && !state.otpAutoSubmitting){
				submitTimer=window.setTimeout(function(){
					if(String(input.value||'').replace(/\D/g,'').length!==length || state.otpAutoSubmitting){return;}
					state.otpAutoSubmitting=true;
					if(typeof verifyForm.requestSubmit==='function'){verifyForm.requestSubmit();}
					else{verifyForm.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));}
				},250);
			}
		}
		input.addEventListener('input',normalizeAndMaybeSubmit);
		input.addEventListener('paste',function(){window.setTimeout(normalizeAndMaybeSubmit,0);});
	}

	function startOtpCountdown(wrapper,seconds){ var btn=q('[data-cas-resend-otp]',wrapper), text=q('[data-cas-countdown-text]',wrapper), remaining=parseInt(seconds,10)||60; if(!btn){return;} if(state.countdownTimer){window.clearInterval(state.countdownTimer);} function render(){ if(remaining>0){ btn.disabled=true; btn.textContent='Resend OTP in '+remaining+'s'; if(text){text.textContent='You can request another OTP in '+remaining+' seconds.';} } else { btn.disabled=false; btn.textContent='Resend OTP'; if(text){text.textContent='Did not receive the OTP? You can resend now.';} } } render(); state.countdownTimer=window.setInterval(function(){ remaining-=1; render(); if(remaining<=0){window.clearInterval(state.countdownTimer); state.countdownTimer=null;} },1000); }

	function initLogin(){ var w=q('[data-cas-component="login"]'); if(!w){return;} var sendForm=q('[data-cas-form="send-otp"]',w), verifyForm=q('[data-cas-form="verify-otp"]',w), profileForm=q('[data-cas-form="select-profile"]',w), profileList=q('[data-cas-profile-list]',w), resend=q('[data-cas-resend-otp]',w), existingSection=q('[data-cas-existing-profile-section]',w), newProfile=q('[data-cas-new-profile-form]',w), showNew=q('[data-cas-show-new-profile]',w), hideNew=q('[data-cas-hide-new-profile]',w);
		initOtpInput(w,verifyForm);
		function showNewProfileOnly(noProfiles){ if(existingSection){existingSection.hidden=!!noProfiles;} if(newProfile){newProfile.hidden=false;} }
		function sendOtp(mobile,isResend){ clearAlert(w); loading(w,true); state.otpAutoSubmitting=false; var otpField=q('[name="otp"]',verifyForm), otpMobile=q('[name="mobile"]',verifyForm); if(otpField){otpField.value='';} if(otpMobile){otpMobile.value=mobile||'';} startWebOtp(w,verifyForm); if(resend){resend.disabled=true;} ajax('cas_send_otp',{mobile:mobile}).then(function(json){ if(json&&json.success){ var m=json.data.mobile||mobile, emailWrap=q('[data-cas-email-otp-label-wrap]',w), emailLabel=q('[data-cas-email-otp-label]',w), devWrap=q('[data-cas-development-otp]',w), devCode=q('[data-cas-development-otp-code]',w), deliveryLabel=q('[data-cas-otp-delivery-label]',w); state.loginMobile=m; q('[name="mobile"]',verifyForm).value=m; q('[data-cas-mobile-label]',w).textContent=m; var verifiedDisplay=q('[data-cas-verified-mobile-display]',w); if(verifiedDisplay){verifiedDisplay.textContent=m;} if(emailWrap&&emailLabel){ if(json.data.email_otp_sent&&json.data.email){ emailLabel.textContent=json.data.email; emailWrap.hidden=false; } else { emailLabel.textContent=''; emailWrap.hidden=true; } } if(devWrap&&devCode){ if(json.data.development_mode&&json.data.development_otp){devCode.textContent=json.data.development_otp;devWrap.hidden=false;}else{devCode.textContent='';devWrap.hidden=true;} } if(deliveryLabel){ deliveryLabel.firstChild.textContent=json.data.development_mode?'Development OTP is ready for ':'OTP has been sent to '; } setStep(w,'login','otp'); q('[name="otp"]',verifyForm).focus(); startOtpCountdown(w,json.data.resend_cooldown_seconds||60); alertBox(w,json.data.development_mode?'Development OTP created. No SMS or email was sent.':(isResend?'OTP resent successfully.':'OTP sent successfully.'),'success'); } else { stopWebOtp(); alertBox(w,json&&json.data&&json.data.message?json.data.message:'Could not send OTP.','error'); if(resend){resend.disabled=false;resend.textContent='Resend OTP';} } }).catch(function(){stopWebOtp();alertBox(w,'Could not send OTP. Please try again.','error');}).finally(function(){loading(w,false);}); }
		if(sendForm){ sendForm.addEventListener('submit',function(e){e.preventDefault(); sendOtp(q('[name="mobile"]',sendForm).value,false);}); }
		if(resend){ resend.addEventListener('click',function(){ if(resend.disabled){return;} sendOtp(state.loginMobile || q('[name="mobile"]',verifyForm).value,true); }); }
		if(showNew){ showNew.addEventListener('click',function(){showNewProfileOnly(false);}); }
		if(hideNew){ hideNew.addEventListener('click',function(){ if(existingSection){existingSection.hidden=false;} if(newProfile){newProfile.hidden=true;} }); }
		if(verifyForm){ verifyForm.addEventListener('submit',function(e){ e.preventDefault(); stopWebOtp(); clearAlert(w); loading(w,true); ajax('cas_verify_otp',{mobile:q('[name="mobile"]',verifyForm).value||state.loginMobile,otp:q('[name="otp"]',verifyForm).value}).then(function(json){ if(json&&json.success&&json.data.action==='auto_login'){ window.location.href=json.data.redirect||cfg().dashboardPageUrl||'/'; return; } if(json&&json.success){ profileList.innerHTML=''; (json.data.profiles||[]).forEach(function(profile){ var b=document.createElement('button'), name=document.createElement('strong'), meta=document.createElement('span'); b.type='button'; b.className='cas-profile-option'; b.dataset.profileId=profile.id; name.textContent=profile.full_name||'Patient'; meta.textContent=(profile.mobile||'')+(profile.gender?' — '+profile.gender:''); b.appendChild(name); b.appendChild(meta); b.addEventListener('click',function(){ loading(w,true); ajax('cas_select_profile',{profile_id:profile.id}).then(function(r){ if(r&&r.success){window.location.href=r.data.redirect||cfg().dashboardPageUrl||'/';} else {alertBox(w,r&&r.data&&r.data.message?r.data.message:'Could not select profile.','error');} }).finally(function(){loading(w,false);}); }); profileList.appendChild(b); }); setStep(w,'login','profile'); if(!profileList.children.length){ alertBox(w,'OTP verified. Create a new patient profile.','success'); if(profileList){profileList.innerHTML='<p class="cas-muted">No existing profile found. Create a new profile below.</p>';} showNewProfileOnly(true); } else { if(existingSection){existingSection.hidden=false;} if(newProfile){newProfile.hidden=true;} alertBox(w,'OTP verified. Select or create a profile.','success'); } } else { state.otpAutoSubmitting=false; alertBox(w,json&&json.data&&json.data.message?json.data.message:'Could not verify OTP.','error'); } }).catch(function(){state.otpAutoSubmitting=false;alertBox(w,'Could not verify OTP. Please try again.','error');}).finally(function(){loading(w,false);}); }); }
		if(profileForm){ profileForm.addEventListener('submit',function(e){ e.preventDefault(); loading(w,true); ajax('cas_select_profile',serializeForm(profileForm)).then(function(json){ if(json&&json.success){window.location.href=json.data.redirect||cfg().dashboardPageUrl||'/';} else {alertBox(w,json&&json.data&&json.data.message?json.data.message:'Could not create profile.','error');} }).finally(function(){loading(w,false);}); }); }
		qa('[data-cas-back-step]',w).forEach(function(b){ b.addEventListener('click',function(){ var target=b.getAttribute('data-cas-back-step'); clearAlert(w); if(target==='mobile'){ stopWebOtp(); state.otpAutoSubmitting=false; var otpInput=q('[name="otp"]',verifyForm); if(otpInput){otpInput.value='';} } setStep(w,'login',target); }); });
	}

	function initProfileForms(){
		function rememberProfileValues(form){ qa('[data-cas-profile-field]',form).forEach(function(field){ field.setAttribute('data-cas-initial-value',field.value||''); }); }
		function restoreProfileValues(form){ qa('[data-cas-profile-field]',form).forEach(function(field){ field.value=field.getAttribute('data-cas-initial-value')||''; }); }
		function titleValue(value){ return value ? String(value).replace(/(^|\s)\S/g,function(l){return l.toUpperCase();}) : '—'; }
		function updateProfileSummary(form){
			var card=form.closest('[data-cas-component="profile"]')||form.closest('.cas-card')||document.body;
			qa('[data-cas-profile-summary-value]',card).forEach(function(target){
				var key=target.getAttribute('data-cas-profile-summary-value'), field=q('[name="'+key+'"]',form), value=field?field.value:'';
				if(key==='gender'){ value=titleValue(value); }
				target.textContent=value||'—';
			});
		}
		function setProfileEditing(form,on){
			var card=form.closest('[data-cas-component="profile"]')||form.closest('.cas-card')||document.body, edit=q('[data-cas-profile-edit]',card), actions=q('[data-cas-profile-actions]',form), summary=q('[data-cas-profile-summary]',card);
			qa('[data-cas-profile-field]',form).forEach(function(field){ if(field.tagName==='SELECT'){ field.disabled=!on; } else { field.readOnly=!on; } });
			form.classList.toggle('cas-profile-editing',!!on); form.classList.toggle('cas-profile-locked',!on); form.hidden=!on;
			if(summary){summary.hidden=!!on;} if(actions){actions.hidden=!on;} if(edit){edit.hidden=!!on;}
			if(on){ var first=q('[data-cas-profile-field]',form); if(first){first.focus();} }
		}
		qa('[data-cas-form="update-profile"]').forEach(function(form){
			var card=form.closest('[data-cas-component="profile"]')||form.closest('.cas-card')||document.body, edit=q('[data-cas-profile-edit]',card), cancel=q('[data-cas-profile-cancel]',form);
			rememberProfileValues(form); setProfileEditing(form,false);
			if(edit){ edit.addEventListener('click',function(){ clearAlert(card); rememberProfileValues(form); setProfileEditing(form,true); }); }
			if(cancel){ cancel.addEventListener('click',function(){ restoreProfileValues(form); clearAlert(card); setProfileEditing(form,false); }); }
		});
		qa('[data-cas-form="update-profile"], [data-cas-form="add-family-member"]').forEach(function(form){ var w=form.closest('[data-cas-component]')||form.closest('.cas-card')||document.body; form.addEventListener('submit',function(e){ e.preventDefault(); clearAlert(w); loading(w,true); ajax(form.getAttribute('data-cas-form')==='update-profile'?'cas_update_profile':'cas_add_family_member', serializeForm(form)).then(function(json){ var ok=json&&json.success; alertBox(w,json&&json.data&&json.data.message?json.data.message:(ok?'Saved.':'Could not save.'),ok?'success':'error'); if(ok&&form.getAttribute('data-cas-form')==='update-profile'){ rememberProfileValues(form); updateProfileSummary(form); setProfileEditing(form,false); } if(ok&&form.getAttribute('data-cas-form')==='add-family-member'){ window.setTimeout(function(){window.location.reload();},800); } }).catch(function(){alertBox(w,'Could not save. Please try again.','error');}).finally(function(){loading(w,false);}); }); });
		qa('[data-cas-toggle-family]').forEach(function(btn){ btn.addEventListener('click',function(){ var card=btn.closest('[data-family-member-id]'), details=q('.cas-family-details',card), expanded=btn.getAttribute('aria-expanded')==='true'; if(!details){return;} details.hidden=expanded; btn.setAttribute('aria-expanded',expanded?'false':'true'); card.classList.toggle('is-open',!expanded); }); });
		qa('[data-cas-edit-family]').forEach(function(btn){ btn.addEventListener('click',function(){ var card=btn.closest('[data-family-member-id]'), form=q('[data-cas-form="edit-family-member"]',card); if(form){form.hidden=!form.hidden;} }); });
		qa('[data-cas-form="edit-family-member"]').forEach(function(form){ var w=form.closest('[data-cas-component]')||document.body; form.addEventListener('submit',function(e){ e.preventDefault(); loading(w,true); ajax('cas_update_family_member',serializeForm(form)).then(function(json){ alertBox(w,json&&json.data&&json.data.message?json.data.message:'Saved.',json&&json.success?'success':'error'); if(json&&json.success){setTimeout(function(){location.reload();},800);} }).finally(function(){loading(w,false);}); }); });
		qa('[data-cas-deactivate-family],[data-cas-delete-family]').forEach(function(btn){ btn.addEventListener('click',function(){ var card=btn.closest('[data-family-member-id]'), id=card?card.getAttribute('data-family-member-id'):0, action=btn.hasAttribute('data-cas-delete-family')?'cas_delete_family_member':'cas_deactivate_family_member'; if(!id){return;} if(!confirm(action==='cas_delete_family_member'?'Delete this family member?':'Deactivate this family member?')){return;} var w=btn.closest('[data-cas-component]')||document.body; loading(w,true); ajax(action,{family_member_id:id}).then(function(json){ alertBox(w,json&&json.data&&json.data.message?json.data.message:'Done.',json&&json.success?'success':'error'); if(json&&json.success){setTimeout(function(){location.reload();},800);} }).finally(function(){loading(w,false);}); }); }); }
	function initBooking(){
		var w=q('[data-cas-component="booking"]'); if(!w){return;}
		var form=q('[data-cas-form="book-appointment"]',w), grid=q('[data-cas-serial-grid]',w), doctor=q('[name="doctor_id"]',w), patient=q('[name="patient_id"]',w), date=q('[name="appointment_date"]',w), serialInput=q('[data-cas-selected-serial]',w), timeInput=q('[data-cas-selected-reporting-time]',w), waiting=q('[data-cas-waiting-box]',w), queue=q('[data-cas-queue-number]',w), waitingSuccess=q('[data-cas-waiting-success]',w), serialActions=q('[data-cas-booking-serial-actions]',w), terms=q('[data-cas-booking-terms]',w), serialFallback=q('[data-cas-load-serials]',w), serialDialog=q('[data-cas-serial-confirm-dialog]',w), serialDialogTitle=q('[data-cas-serial-confirm-title]',w), serialDialogMessage=q('[data-cas-serial-confirm-message]',w), serialDialogYes=q('[data-cas-serial-confirm-yes]',w), reviewButton=q('[data-cas-next-step="confirm"]',w), pendingSerialChoice=null, lastDialogTrigger=null;
		var addRelativeButton=q('[data-cas-open-relative-panel]',w), relativePanel=q('[data-cas-quick-relative-panel]',w), cancelRelativeButton=q('[data-cas-cancel-relative]',w), saveRelativeButton=q('[data-cas-save-relative]',w);
		var editId=parseInt(w.getAttribute('data-cas-edit-appointment-id')||'0',10)||0, isEditing=w.getAttribute('data-cas-editing')==='1', presetSerial=String(w.getAttribute('data-cas-edit-serial')||''), blocked=false, isWaiting=false, autoSerialTimer=null, availabilityPollTimer=null, lastSerialRequestKey='';

		function text(selector,value){ var el=q(selector,w); if(el){el.textContent=value||'';} }
		function setRelativePanel(open){
			if(!relativePanel){return;}
			relativePanel.hidden=!open;
			if(addRelativeButton){ addRelativeButton.setAttribute('aria-expanded',open?'true':'false'); }
			if(open){ var first=q('[data-cas-relative-name]',relativePanel); if(first){window.setTimeout(function(){first.focus();},0);} }
		}
		function clearRelativeFields(){
			if(!relativePanel){return;}
			qa('input,select',relativePanel).forEach(function(field){ field.value=''; });
		}
		/* Keep Review Booking unavailable until one serial is selected. A fully
		 * booked date may still continue as a waiting-list request. */
		function updateReviewActions(){
			var canReview=!blocked && (isWaiting || !!state.serial);
			qa('[data-cas-next-step="confirm"]',w).forEach(function(button){
				button.disabled=!canReview;
				button.setAttribute('aria-disabled',!canReview?'true':'false');
			});
		}
		function setBlocked(on,message){
			blocked=!!on;
			var n=q('[data-cas-active-appointment-warning]',w);
			if(n){n.hidden=!on;n.textContent=message||'';}
			updateReviewActions();
		}
		function resetSerials(){
			state.serial=''; state.timeRaw=''; state.timeLabel=''; isWaiting=false;
			if(serialInput){serialInput.value='';}
			if(timeInput){timeInput.value='';}
			if(grid){grid.innerHTML='';}
			if(waiting){waiting.hidden=true;}
			if(waitingSuccess){waitingSuccess.hidden=true;waitingSuccess.textContent='';}
			if(serialActions){serialActions.hidden=false;}
			updateReviewActions();
		}
		function selectedLabel(control, fallback){
			if(!control){ return fallback||''; }
			// Single-doctor mode uses a hidden input instead of a <select>.
			// Do not access .options unless this control is actually a select.
			if(control.options && typeof control.selectedIndex !== 'undefined' && control.options[control.selectedIndex]){
				return control.options[control.selectedIndex].textContent || fallback || '';
			}
			return control.getAttribute('data-cas-doctor-name') || fallback || '';
		}
		function setBookingState(){
			state.doctorId=doctor&&doctor.value?doctor.value:'';
			state.doctorName=selectedLabel(doctor,'');
			state.patientId=patient&&patient.value?patient.value:'';
			state.patientName=selectedLabel(patient,'');
			state.date=date&&date.value?date.value:'';
		}
		function refreshPatientSummary(){
			setBookingState();
			text('[data-cas-patient-date]',dateLabel(state.date));
			text('[data-cas-patient-serial]',isWaiting?'Waiting list':(state.serial?'#'+state.serial+' · '+state.timeLabel:''));
			text('[data-cas-selected-date-label]',dateLabel(state.date));
		}
		function checkActive(){
			if(isEditing||!patient||!patient.value){setBlocked(false,'');return Promise.resolve();}
			return ajax('cas_get_patient_active_appointment',{patient_id:patient.value}).then(function(r){
				if(r&&r.success&&r.data&&r.data.has_active){
					var d=r.data;
					setBlocked(true,'This patient already has an active appointment'+(d.doctor_name?' with '+d.doctor_name:'')+' on '+dateLabel(d.date)+(d.serial_number?' (Serial '+d.serial_number+')':'')+'. Modify or cancel it from My Appointments before booking another one.');
				}else{setBlocked(false,'');}
			});
		}
		function loadDateAvailability(){
			if(!doctor||!date||!doctor.value||!date.value){return;}
			ajax('cas_get_available_dates',{doctor_id:doctor.value,month:date.value.substring(0,7)}).then(function(r){
				var h=q('[data-cas-date-help]',w); if(!h||!r||!r.success){return;}
				var f=(r.data.dates||[]).filter(function(x){return x.date===date.value;})[0]; if(!f){return;}
				h.textContent=f.is_holiday?t('dateHoliday','This date is a holiday.'):(!f.is_active_day?t('inactiveDay','This is not an active chamber day.'):f.available_count+' '+t('serialsAvailable','serial(s) available for this date.'));
			});
		}
		function applySerialChoice(choice, shouldScroll){
			if(!choice){return;}
			qa('.cas-serial-tile',grid).forEach(function(x){x.classList.remove('is-selected');x.setAttribute('aria-pressed','false');});
			choice.tile.classList.add('is-selected'); choice.tile.setAttribute('aria-pressed','true');
			state.serial=choice.serial; state.timeRaw=String(choice.item.reporting_time||''); state.timeLabel=choice.time;
			if(serialInput){serialInput.value=choice.serial;} if(timeInput){timeInput.value=state.timeRaw;}
			clearAlert(w); refreshPatientSummary(); updateReviewActions();
			if(shouldScroll&&reviewButton){window.setTimeout(function(){reviewButton.scrollIntoView({behavior:'smooth',block:'center'});reviewButton.focus({preventScroll:true});},120);}
		}
		function closeSerialDialog(restoreFocus){
			if(!serialDialog){return;}
			serialDialog.hidden=true; document.body.classList.remove('cas-dialog-open'); pendingSerialChoice=null;
			if(restoreFocus&&lastDialogTrigger){lastDialogTrigger.focus();}
		}
		function openSerialDialog(choice){
			if(!serialDialog){applySerialChoice(choice,true);return;}
			pendingSerialChoice=choice; lastDialogTrigger=choice.tile;
			if(serialDialogTitle){serialDialogTitle.textContent=t('serialConfirmTitle','Confirm serial selection');}
			if(serialDialogMessage){serialDialogMessage.textContent=t('serialConfirmMessage','You have selected serial %s. Are you sure?').replace('%s','#'+choice.serial);}
			qa('[data-cas-serial-confirm-cancel]',serialDialog).forEach(function(btn){if(btn.tagName==='BUTTON'){btn.textContent=t('serialConfirmNo','No, choose another');}});
			if(serialDialogYes){serialDialogYes.textContent=t('serialConfirmYes','Yes, select it');}
			serialDialog.hidden=false; document.body.classList.add('cas-dialog-open'); window.setTimeout(function(){if(serialDialogYes){serialDialogYes.focus();}},20);
		}
		if(serialDialog){
			qa('[data-cas-serial-confirm-cancel]',serialDialog).forEach(function(el){el.addEventListener('click',function(){closeSerialDialog(true);});});
			if(serialDialogYes){serialDialogYes.addEventListener('click',function(){var choice=pendingSerialChoice;closeSerialDialog(false);applySerialChoice(choice,true);});}
			document.addEventListener('keydown',function(e){if(serialDialog.hidden){return;}if(e.key==='Escape'){e.preventDefault();closeSerialDialog(true);}if(e.key==='Tab'){var buttons=qa('button:not([disabled])',serialDialog);if(!buttons.length){return;}var first=buttons[0],last=buttons[buttons.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
		}
		function renderSerials(items, selectedSerial){
			if(!grid){return;}
			grid.innerHTML='';
			(items||[]).forEach(function(item){
				var serial=String(item.serial||''), time=String(item.reporting_time_display||item.reporting_time||''), tile=document.createElement('button'), choice;
				tile.type='button'; tile.className='cas-serial-tile'; tile.setAttribute('aria-label',t('serialLabel','Serial')+' '+serial+' '+t('reportingTimeLabel','Reporting time')+' '+time); tile.setAttribute('aria-pressed','false');
				tile.innerHTML='<span class="cas-serial-label">'+t('serialLabel','Serial')+' '+serial+'</span><strong class="cas-serial-number">#'+serial+'</strong><span class="cas-serial-time">'+time+'</span>';
				choice={serial:serial,time:time,item:item,tile:tile};
				tile.addEventListener('click',function(){openSerialDialog(choice);}); grid.appendChild(tile);
				if((selectedSerial&&selectedSerial===serial)||(isEditing&&presetSerial===serial)){window.setTimeout(function(){applySerialChoice(choice,false);},0);}
			});
		}
		function updateSerialFallback(show){ if(serialFallback){ serialFallback.hidden=!show; } }
		function loadSerials(force,silent,keepSelection){
			if(!doctor||!date||!doctor.value||!date.value){updateSerialFallback(false);return Promise.resolve();}
			var requestKey=[doctor.value,date.value,editId].join('|');
			if(!force&&requestKey===lastSerialRequestKey&&grid&&grid.children.length){return Promise.resolve();}
			var selectedBefore=keepSelection?String(state.serial||''):'';
			lastSerialRequestKey=requestKey; updateSerialFallback(false); if(!silent){clearAlert(w);loading(w,true);} setBookingState();
			return ajax('cas_get_available_serials',{doctor_id:doctor.value,appointment_date:date.value,appointment_id:editId}).then(function(r){
				if(r&&r.success&&r.data){
					var items=r.data.serials||[], kept=null;
					if(selectedBefore){items.some(function(item){if(String(item.serial||'')===selectedBefore){kept=item;return true;}return false;});}
					if(!kept){state.serial='';state.timeRaw='';state.timeLabel='';if(serialInput){serialInput.value='';}if(timeInput){timeInput.value='';}}
					isWaiting=!!r.data.is_fully_booked; renderSerials(items,kept?selectedBefore:'');
					if(waiting){waiting.hidden=isEditing||!r.data.is_fully_booked;} if(queue){queue.textContent=r.data.queue_number||'';}
					refreshPatientSummary();updateReviewActions();
					if(!silent){setStep(w,'booking','serial');alertBox(w,r.data.is_fully_booked?t('fullyBooked','This date is fully booked. You may join the waiting list after choosing a patient.'):t('chooseSerial','Choose one available serial below.'),'info');}
					if(selectedBefore&&!kept){alertBox(w,t('serialJustTaken','Your selected serial was just booked by another user. Please choose another serial.'),'error');}
					return r;
				}
				lastSerialRequestKey='';if(!silent){updateSerialFallback(true);alertBox(w,r&&r.data&&r.data.message?r.data.message:t('serialLoadFailed','Could not load serials. Tap “Show available serials” to try again.'),'error');}return r;
			}).catch(function(){lastSerialRequestKey='';if(!silent){updateSerialFallback(true);alertBox(w,t('serialAutoLoadFailed','Could not load serials automatically. Tap “Show available serials” to try again.'),'error');}}).finally(function(){if(!silent){loading(w,false);}updateReviewActions();});
		}
		function startAvailabilityPolling(){
			var seconds=Math.max(5,parseInt(cfg().availabilityPollSeconds||12,10)||12);
			function poll(){var serialStep=q('[data-cas-booking-step="serial"].is-active',w),confirmStep=q('[data-cas-booking-step="confirm"].is-active',w);if(document.hidden||(!serialStep&&!confirmStep)||!doctor||!date||!doctor.value||!date.value){return;}loadSerials(true,true,true);}
			availabilityPollTimer=window.setInterval(poll,seconds*1000);document.addEventListener('visibilitychange',function(){if(!document.hidden){poll();}});
		}

		function openSerialsWhenReady(force){
			if(!doctor||!date||!doctor.value||!date.value){
				if(date&&date.value){ updateSerialFallback(true); }
				return;
			}
			loadDateAvailability(); loadSerials(!!force,false,false);
		}
		function queueAutoSerialLoad(){
			if(autoSerialTimer){ window.clearTimeout(autoSerialTimer); }
			/* A native date picker can emit input and change. Use one non-forced
			 * request so it never reloads after the patient taps a serial. */
			autoSerialTimer=window.setTimeout(function(){ openSerialsWhenReady(false); },100);
		}
		function goPatient(){
			if(!isWaiting&&!state.serial){alertBox(w,'Please select a serial number first.','error');return;}
			refreshPatientSummary(); setStep(w,'booking','patient');
			checkActive();
		}
		function goConfirm(){
			setBookingState();
			if(!patient||!patient.value){alertBox(w,t('patientRequired','Please choose the patient who will attend this appointment.'),'error');setStep(w,'booking','date');return;}
			checkActive().then(function(){
				if(blocked){return;}
				if(!isWaiting&&!state.serial){alertBox(w,t('serialRequired','Please select a serial first.'),'error');setStep(w,'booking','serial');return;}
				text('[data-cas-confirm-patient]',state.patientName);
				text('[data-cas-confirm-doctor]',state.doctorName);
				text('[data-cas-confirm-date]',dateLabel(state.date));
				text('[data-cas-confirm-serial]',isWaiting?'Waiting list':state.serial);
				text('[data-cas-confirm-reporting-time]',isWaiting?'Queue request':state.timeLabel);
				setStep(w,'booking','confirm'); clearAlert(w); var confirmHeading=q('[data-cas-confirm-booking-heading]',w); if(confirmHeading){ window.setTimeout(function(){ confirmHeading.scrollIntoView({behavior:'smooth',block:'start'}); try{confirmHeading.focus({preventScroll:true});}catch(e){confirmHeading.focus();} },80); }
			});
		}
		function joinWaitingList(){
			loading(w,true);
			ajax('cas_join_waiting_list',{patient_id:patient?patient.value:'',doctor_id:doctor?doctor.value:'',appointment_date:date?date.value:''}).then(function(r){
				alertBox(w,r&&r.data&&r.data.message?r.data.message:(r&&r.success?t('waitingSaved','Waiting list request saved.'):t('waitingFailed','Could not join the waiting list.')),r&&r.success?'success':'error');
				if(r&&r.success&&cfg().appointmentsPageUrl){window.setTimeout(function(){window.location.href=cfg().appointmentsPageUrl;},900);}
			}).catch(function(){alertBox(w,t('waitingFailed','Could not join the waiting list.'),'error');}).finally(function(){loading(w,false);});
		}

		qa('[data-cas-next-step]',w).forEach(function(button){button.addEventListener('click',function(){
			var next=button.getAttribute('data-cas-next-step');
			if(next==='serial'){openSerialsWhenReady(true); return;}
			if(next==='patient'){goPatient(); return;}
			if(next==='confirm'){goConfirm();}
		});});
		qa('[data-cas-back-step]',w).forEach(function(button){button.addEventListener('click',function(){clearAlert(w);setStep(w,'booking',button.getAttribute('data-cas-back-step'));});});
		qa('[data-cas-change-step]',w).forEach(function(button){button.addEventListener('click',function(){clearAlert(w);setStep(w,'booking',button.getAttribute('data-cas-change-step'));});});
		if(serialFallback){ serialFallback.addEventListener('click',function(){ openSerialsWhenReady(true); }); }
		if(doctor){doctor.addEventListener('change',function(){presetSerial='';resetSerials();queueAutoSerialLoad();});}
		if(date){
			date.addEventListener('input',function(){presetSerial='';resetSerials();queueAutoSerialLoad();});
			date.addEventListener('change',function(){presetSerial='';resetSerials();queueAutoSerialLoad();});
			/* Do not reload on blur. A mobile tap on a serial blurs the date input. */
		}
		if(patient){patient.addEventListener('change',function(){setBookingState();refreshPatientSummary();checkActive();});}
		if(addRelativeButton){ addRelativeButton.addEventListener('click',function(){ clearAlert(w); setRelativePanel(true); }); }
		if(cancelRelativeButton){ cancelRelativeButton.addEventListener('click',function(){ clearRelativeFields(); setRelativePanel(false); }); }
		if(saveRelativeButton){ saveRelativeButton.addEventListener('click',function(){
			if(!relativePanel||!patient){return;}
			var nameField=q('[data-cas-relative-name]',relativePanel), relationField=q('[data-cas-relative-relation]',relativePanel), ageField=q('[data-cas-relative-age]',relativePanel), genderField=q('[data-cas-relative-gender]',relativePanel), bloodField=q('[data-cas-relative-blood-group]',relativePanel);
			var relativeName=nameField?nameField.value.trim():'', relation=relationField?relationField.value:'';
			if(!relativeName||!relation){ alertBox(w,t('relativeRequired','Please enter the relative name and select the relationship.'),'error'); return; }
			loading(w,true);
			ajax('cas_add_family_member',{full_name:relativeName,relation:relation,age:ageField?ageField.value:'',gender:genderField?genderField.value:'',blood_group:bloodField?bloodField.value:''}).then(function(r){
				if(r&&r.success&&r.data&&r.data.profile){
					var profile=r.data.profile, option=document.createElement('option');
					option.value=String(profile.id||'');
					option.textContent=(profile.full_name||relativeName)+' — '+(profile.mobile||'');
					patient.appendChild(option);
					patient.value=option.value;
					clearRelativeFields(); setRelativePanel(false); setBookingState(); refreshPatientSummary();
					checkActive();
					alertBox(w,r.data.message||t('relativeAdded','Relative added and selected for this appointment.'),'success');
				}else{ alertBox(w,r&&r.data&&r.data.message?r.data.message:t('relativeAddFailed','Could not add this relative. Please try again.'),'error'); }
			}).catch(function(){alertBox(w,t('relativeAddFailed','Could not add this relative. Please try again.'),'error');}).finally(function(){loading(w,false);});
		}); }
		if(form){form.addEventListener('submit',function(e){
			e.preventDefault();
			if(terms&&!terms.checked){alertBox(w,t('termsRequired','Please agree to the appointment and privacy policy before continuing.'),'error');return;}
			setBookingState();
			checkActive().then(function(){
				if(blocked){return;}
				if(isWaiting){joinWaitingList();return;}
				if(!state.serial){alertBox(w,t('serialRequired','Please select a serial first.'),'error');setStep(w,'booking','serial');return;}
				if(!window.confirm(isEditing?t('confirmChanges','Save these appointment changes?'):((cfg().i18n&&cfg().i18n.confirmBooking)||'Confirm this appointment?'))){return;}
				loading(w,true);
				ajax(isEditing?'cas_update_appointment':'cas_book_appointment',{appointment_id:editId,patient_id:patient?patient.value:'',doctor_id:doctor.value,appointment_date:date.value,serial_number:serialInput.value,notes:q('[name="notes"]',form).value}).then(function(r){
					alertBox(w,r&&r.data&&r.data.message?r.data.message:(r&&r.success?(isEditing?t('appointmentUpdated','Appointment updated successfully.'):t('appointmentBooked','Appointment booked successfully.')):t('saveFailed','Could not save appointment.')),r&&r.success?'success':'error');
					if(r&&r.success&&r.data&&r.data.profile_incomplete&&!isEditing){
						var box=document.createElement('div'); box.className='cas-profile-booking-reminder';
						box.innerHTML='<div class="cas-profile-booking-reminder-panel"><strong>Your appointment has been booked successfully.</strong><p>You may complete your profile now to help the chamber provide better service.</p><div><a class="cas-button cas-button-primary" href="'+escapeHTML(r.data.profile_url||cfg().dashboardPageUrl||'/')+'">Complete Profile</a><button type="button" class="cas-button cas-button-link">Later</button></div></div>';
						document.body.appendChild(box); q('button',box).addEventListener('click',function(){box.remove(); if(cfg().appointmentsPageUrl){window.location.href=cfg().appointmentsPageUrl;}}); return;
					}
					if(r&&r.success&&cfg().appointmentsPageUrl){window.setTimeout(function(){window.location.href=cfg().appointmentsPageUrl;},900);}
				}).catch(function(){alertBox(w,t('saveFailed','Could not save appointment.'),'error');}).finally(function(){loading(w,false);});
			});
		});}
		setBookingState(); updateReviewActions(); startAvailabilityPolling();
		if(patient&&patient.value&&!isEditing){checkActive();}
		if(isEditing){ if(doctor&&doctor.value&&date&&date.value){window.setTimeout(openSerialsWhenReady,120);}}
	}

	function initAppointmentActions(){var w=q('[data-cas-component="my-appointments"]');if(!w){return;}qa('[data-cas-cancel-appointment]',w).forEach(function(button){button.addEventListener('click',function(){var id=button.getAttribute('data-cas-cancel-appointment');if(!id||!window.confirm('Cancel this appointment?')){return;}button.disabled=true;ajax('cas_cancel_appointment',{appointment_id:id}).then(function(r){var box=q('[data-cas-alert]',w);if(box){box.hidden=false;box.className='cas-alert cas-alert-'+(r&&r.success?'success':'error');box.textContent=r&&r.data&&r.data.message?r.data.message:(r&&r.success?'Appointment cancelled.':'Could not cancel appointment.');}if(r&&r.success){window.setTimeout(function(){window.location.reload();},850);}else{button.disabled=false;}}).catch(function(){button.disabled=false;});});});}

	function initMessages(){
		var w=q('[data-cas-component="messages"]'); if(!w){return;}
		var form=q('[data-cas-form="send-message"]',w), thread=q('[data-cas-message-thread]',w), refresh=q('[data-cas-refresh-messages]',w), fileInput=q('[name="attachment"]',w);
		function attachmentHTML(a){
			if(!a||!a.url){return '';}
			var name=escapeHTML(a.name||'Attachment'), url=escapeHTML(a.url);
			if(a.is_image){ return '<a class="cas-message-attachment cas-message-attachment-image" href="'+url+'" target="_blank" rel="noopener"><img src="'+url+'" alt="'+name+'"><span>'+name+'</span></a>'; }
			return '<a class="cas-message-attachment cas-message-attachment-file" href="'+url+'" target="_blank" rel="noopener">📎 '+name+'</a>';
		}
		function render(messages){
			if(!thread){return;}
			thread.innerHTML='';
			(messages||[]).forEach(function(m){
				var div=document.createElement('div'), text=m.message?'<p>'+escapeHTML(m.message)+'</p>':'';
				div.className='cas-message cas-message-'+m.direction;
				div.innerHTML='<div class="cas-message-bubble">'+text+attachmentHTML(m.attachment)+'<small>'+escapeHTML(m.created_at||'')+'</small></div>';
				thread.appendChild(div);
			});
			thread.scrollTop=thread.scrollHeight;
		}
		function refreshMessages(){ ajax('cas_get_messages',{}).then(function(json){ if(json&&json.success){render(json.data.messages||[]);} }); }
		function parseFetchResponse(response){
			return response.text().then(function(text){
				try { return JSON.parse(text); }
				catch(e){ return { success:false, data:{ message: text ? text.substring(0,300) : 'Server returned an invalid response while uploading attachment.' } }; }
			});
		}
		function postMessage(){
			var body=new FormData(form);
			body.append('action','cas_send_message');
			body.append('nonce',cfg().nonce||'');
			return fetch(cfg().ajaxUrl,{method:'POST',credentials:'same-origin',body:body}).then(parseFetchResponse);
		}
		if(fileInput){ fileInput.addEventListener('change',function(){ var f=fileInput.files&&fileInput.files[0]; if(f&&f.size>5*1024*1024){ alertBox(w,'Selected file is larger than 5 MB. Please select a smaller file.','error'); fileInput.value=''; } }); }
		if(refresh){ refresh.addEventListener('click',refreshMessages); }
		if(thread){ thread.scrollTop=thread.scrollHeight; }
		if(form){ form.addEventListener('submit',function(e){
			e.preventDefault(); clearAlert(w);
			var msg=q('[name="message"]',form), hasText=msg&&msg.value.trim().length>0, hasFile=fileInput&&fileInput.files&&fileInput.files.length>0;
			if(!hasText&&!hasFile){ alertBox(w,'Please write a message or attach a file.','error'); return; }
			loading(w,true);
			alertBox(w,hasFile?'Uploading attachment and sending message...':'Sending message...','info');
			postMessage().then(function(json){
				alertBox(w,json&&json.data&&json.data.message?json.data.message:(json&&json.success?'Message sent.':'Could not send message.'),json&&json.success?'success':'error');
				if(json&&json.success){ form.reset(); refreshMessages(); }
			}).catch(function(){alertBox(w,'Could not send message. Please try again.','error');}).finally(function(){loading(w,false);});
		}); }
	}

	function initMobileMoreMenu(){
		var sheet=q('[data-cas-more-sheet]'), opener=q('[data-cas-more-open]');
		if(!sheet||!opener){return;}
		var lastFocus=null;
		function openSheet(){lastFocus=document.activeElement;sheet.hidden=false;document.body.classList.add('cas-more-open');opener.setAttribute('aria-expanded','true');var close=q('.cas-more-close',sheet);if(close){window.setTimeout(function(){close.focus();},20);}}
		function closeSheet(){sheet.hidden=true;document.body.classList.remove('cas-more-open');opener.setAttribute('aria-expanded','false');if(lastFocus&&lastFocus.focus){lastFocus.focus();}}
		opener.addEventListener('click',openSheet);qa('[data-cas-more-close]',sheet).forEach(function(el){el.addEventListener('click',closeSheet);});
		document.addEventListener('keydown',function(e){if(sheet.hidden){return;}if(e.key==='Escape'){e.preventDefault();closeSheet();return;}if(e.key==='Tab'){var focusable=qa('a[href],button:not([disabled])',sheet);if(!focusable.length){return;}var first=focusable[0],last=focusable[focusable.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
	}

	function initLogout(){ qa('[data-cas-logout]').forEach(function(button){ button.addEventListener('click',function(){ if(!window.confirm((cfg().i18n&&cfg().i18n.confirmLogout)||'Log out?')){return;} ajax('cas_logout',{}).then(function(json){window.location.href=json&&json.data&&json.data.redirect?json.data.redirect:'/';}); }); }); }
	function initLanguageToggle(){qa('[data-cas-language]').forEach(function(button){button.addEventListener('click',function(){var language=button.getAttribute('data-cas-language');if(language!=='bn'&&language!=='en'){return;}document.cookie='cas_language='+language+';path=/;max-age=31536000;SameSite=Lax';var url=new URL(window.location.href);url.searchParams.set('cas_lang',language);window.location.href=url.toString();});});}

	/* Themes such as Neve can put shortcode pages in a narrow article column and
	 * duplicate the page title above the actual patient portal heading. Mark the
	 * page for the responsive CSS and hide only that duplicate theme title. */
	function initResponsivePortalLayout(){
		if(!q('.cas-public-wrap')){ return; }
		document.body.classList.add('cas-has-public-portal');
		qa('h1.entry-title, h1.page-title, .nv-page-title h1, .entry-header .entry-title').forEach(function(title){
			if(!title.closest('.cas-public-wrap')){ title.classList.add('cas-hide-portal-page-title'); }
		});
	}
	ready(function(){ initResponsivePortalLayout(); initMobileMoreMenu(); initLanguageToggle(); initLogin(); initProfileForms(); initBooking(); initAppointmentActions(); initMessages(); initLogout(); qa('[data-cas-dismiss-profile-reminder]').forEach(function(b){b.addEventListener('click',function(){var n=b.closest('[data-cas-profile-completion-notice]');if(n){n.remove();}try{sessionStorage.setItem('casProfileReminderDismissed','1');}catch(e){}});}); try{if(sessionStorage.getItem('casProfileReminderDismissed')==='1'){qa('[data-cas-profile-completion-notice]').forEach(function(n){n.remove();});}}catch(e){} });
})();
