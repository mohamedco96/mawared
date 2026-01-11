(function(){let i=!1,s=!1,r=!1;function c(){document.querySelectorAll("form[wire\\:submit], form.fi-form").forEach(e=>{e.addEventListener("input",function(t){!t.target.disabled&&t.target.type!=="hidden"&&(i=!0)},!0),e.addEventListener("change",function(t){!t.target.disabled&&t.target.type!=="hidden"&&(i=!0)},!0),e.addEventListener("submit",function(){s=!0,i=!1})})}function u(n){if(r)return;r=!0;const e=document.createElement("div");e.style.cssText=`
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        `;const t=document.createElement("div");t.style.cssText=`
            position: relative;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 28rem;
            width: 100%;
            margin: 1rem;
            padding: 1.5rem;
            animation: fadeIn 0.2s ease-out;
        `;const o=document.documentElement.classList.contains("dark");o&&(t.style.backgroundColor="rgb(31, 41, 55)"),t.innerHTML=`
            <style>
                @keyframes fadeIn {
                    from { opacity: 0; transform: scale(0.95); }
                    to { opacity: 1; transform: scale(1); }
                }
            </style>
            <div style="display: flex; align-items: flex-start; gap: 1rem;">
                <div style="flex-shrink: 0;">
                    <svg style="width: 1.5rem; height: 1.5rem; color: rgb(245, 158, 11);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <div style="flex: 1;">
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: ${o?"white":"rgb(17, 24, 39)"}; margin-bottom: 0.5rem;">
                        تغييرات غير محفوظة
                    </h3>
                    <p style="font-size: 0.875rem; color: ${o?"rgb(156, 163, 175)":"rgb(75, 85, 99)"};">
                        لديك تغييرات غير محفوظة. هل تريد المغادرة دون حفظ؟
                    </p>
                </div>
            </div>
            <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" id="unsaved-cancel-btn" style="
                    padding: 0.5rem 1rem;
                    font-size: 0.875rem;
                    font-weight: 500;
                    color: ${o?"rgb(209, 213, 219)":"rgb(55, 65, 81)"};
                    background-color: ${o?"rgb(55, 65, 81)":"white"};
                    border: 1px solid ${o?"rgb(75, 85, 99)":"rgb(209, 213, 219)"};
                    border-radius: 0.5rem;
                    cursor: pointer;
                    transition: all 0.15s;
                ">
                    إلغاء
                </button>
                <button type="button" id="unsaved-leave-btn" style="
                    padding: 0.5rem 1rem;
                    font-size: 0.875rem;
                    font-weight: 500;
                    color: white;
                    background-color: rgb(220, 38, 38);
                    border: none;
                    border-radius: 0.5rem;
                    cursor: pointer;
                    transition: all 0.15s;
                ">
                    المغادرة بدون حفظ
                </button>
            </div>
        `,e.appendChild(t),document.body.appendChild(e);const d=document.getElementById("unsaved-cancel-btn"),a=document.getElementById("unsaved-leave-btn");d.addEventListener("mouseenter",function(){this.style.backgroundColor=o?"rgb(75, 85, 99)":"rgb(249, 250, 251)"}),d.addEventListener("mouseleave",function(){this.style.backgroundColor=o?"rgb(55, 65, 81)":"white"}),a.addEventListener("mouseenter",function(){this.style.backgroundColor="rgb(185, 28, 28)"}),a.addEventListener("mouseleave",function(){this.style.backgroundColor="rgb(220, 38, 38)"}),document.getElementById("unsaved-cancel-btn").addEventListener("click",function(){e.remove(),r=!1,n&&n(!1)}),document.getElementById("unsaved-leave-btn").addEventListener("click",function(){e.remove(),r=!1,i=!1,s=!0,n&&n(!0)}),e.addEventListener("click",function(f){f.target===e&&(e.remove(),r=!1,n&&n(!1))});const l=function(f){f.key==="Escape"&&(e.remove(),r=!1,document.removeEventListener("keydown",l),n&&n(!1))};document.addEventListener("keydown",l)}function m(){document.addEventListener("click",function(e){if(i&&!s&&!r){const t=e.target.closest("a");if(t&&t.href&&!t.getAttribute("wire:click")){const o=window.location.pathname,d=new URL(t.href,window.location.origin).pathname;o!==d&&(e.preventDefault(),e.stopPropagation(),u(function(a){a&&(window.location.href=t.href)}))}}},!0);let n=!1;window.addEventListener("popstate",function(e){i&&!s&&!n&&(history.pushState(null,"",window.location.href),u(function(t){t&&(n=!0,history.back())}))}),window.addEventListener("beforeunload",function(e){if(i&&!s)return e.preventDefault(),e.returnValue="",""})}document.addEventListener("livewire:init",()=>{Livewire.hook("commit",({component:n,commit:e,respond:t,succeed:o,fail:d})=>{o(({snapshot:a,effect:l})=>{(l?.dispatches?.length>0||l?.redirectTo)&&(s=!0,i=!1)})}),Livewire.hook("navigate",({url:n,history:e})=>{if(i&&!s&&!r)return new Promise((t,o)=>{u(function(d){d?t():o()})})})}),document.readyState==="loading"?document.addEventListener("DOMContentLoaded",function(){c(),m()}):(c(),m()),document.addEventListener("livewire:navigated",function(){i=!1,s=!1,r=!1,c()})})();
