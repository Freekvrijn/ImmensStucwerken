import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {spawnSync} from 'node:child_process';
import vm from 'node:vm';
const php=spawnSync('php',['tests/offerte-rekenen.php','--fixtures'],{encoding:'utf8'});
assert.equal(php.status,0,php.stderr);
const fixtures=JSON.parse(php.stdout);
const source=readFileSync('public_html/assets/offerte.js','utf8');
function element(tag){return {tag,children:[],textContent:'',append(...children){this.children.push(...children)},replaceChildren(...children){this.children=children}}}
for(const fixture of fixtures){
  const inputs=fixture.config.diensten.filter((s)=>s.actief).map((s)=>({dataset:{dienst:s.id},value:fixture.input.aantallen[s.id]||'0'}));
  const form={elements:{pand:{value:fixture.input.pand}},querySelectorAll(){return inputs},addEventListener(){}};
  const output=element('output');
  const document={getElementById(id){return {'offerteformulier':form,'offerte-config':{textContent:JSON.stringify(fixture.config)},'offerte-berekening':output}[id]},createElement:element};
  vm.runInNewContext(source,{document,Intl,Array,Number,String,JSON,Math,Error});
  const list=output.children[0];
  assert.equal(list.tag,'dl',fixture.name);
  const last=list.children.at(-1);
  const total=new Intl.NumberFormat('nl-NL',{style:'currency',currency:'EUR'}).format(fixture.result.totaal_cent/100);
  assert.equal(last.children[1].textContent,total,fixture.name);
}
console.log(`${fixtures.length} browserberekeningen komen overeen met de server, inclusief btw, minimum en afronding.`);
