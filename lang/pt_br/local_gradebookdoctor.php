<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Brazilian Portuguese strings.
 *
 * @package   local_gradebookdoctor
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['aggregation'] = 'Agregação';
$string['aggregation:extracreditmean'] = 'Média das notas com crédito extra';
$string['aggregation:max'] = 'Maior nota';
$string['aggregation:mean'] = 'Média das notas';
$string['aggregation:median'] = 'Mediana das notas';
$string['aggregation:min'] = 'Menor nota';
$string['aggregation:mode'] = 'Moda das notas';
$string['aggregation:natural'] = 'Natural';
$string['aggregation:simpleweightedmean'] = 'Média ponderada simples das notas';
$string['aggregation:unknown'] = 'Agregação desconhecida';
$string['aggregation:weightedmean'] = 'Média ponderada das notas';
$string['ai:boundary'] = 'O texto da IA abaixo explica os dados do Moodle; ele não é a fonte do valor da nota.';
$string['attention:info'] = 'Informativo';
$string['attention:review'] = 'Revisar';
$string['compare:excludeda'] = 'A excluído';
$string['compare:excludedb'] = 'B excluído';
$string['compare:flags'] = 'Diferenças de sinalizadores';
$string['compare:gradea'] = 'Nota A';
$string['compare:gradeb'] = 'Nota B';
$string['compare:item'] = 'Item comparado: {$a}';
$string['compare:overridea'] = 'A sobrescrito';
$string['compare:overrideb'] = 'B sobrescrito';
$string['compare:statusa'] = 'Status A';
$string['compare:statusb'] = 'Status B';
$string['compare:studenta'] = 'Aluno A: {$a}';
$string['compare:studentb'] = 'Aluno B: {$a}';
$string['deterministic:boundary'] = 'Valores, status, pesos e sinalizadores abaixo vêm dos registros do livro de notas e das classes de notas do Moodle. O Médico do livro de notas não recalcula a nota final.';
$string['diagnosis:boundary'] = 'Os achados abaixo vêm de verificações determinísticas. “Revisar” significa que a configuração merece atenção, não que esteja necessariamente incorreta.';
$string['diagnosis:categories'] = 'Categorias';
$string['diagnosis:findings'] = 'Achados';
$string['diagnosis:items'] = 'Itens de nota';
$string['diagnosis:summary'] = '{$a->findings} achados: {$a->review} para revisar e {$a->info} informativos.';
$string['edit'] = 'Abrir configuração no livro de notas';
$string['error:aifailed'] = 'A explicação por IA não está disponível para esta solicitação. Verifique tenant, purpose, rota, créditos e permissões do usuário no AI Bridge.';
$string['error:bridgeunavailable'] = 'O local_ai_bridge não está disponível. Os dados de cálculo do Moodle continuam sendo exibidos.';
$string['error:invalidairesponse'] = 'O AI Bridge retornou uma resposta, mas ela não é o objeto JSON válido exigido. Os achados determinísticos continuam sendo exibidos.';
$string['error:invalidgradeitem'] = 'O item de nota selecionado não pertence a este curso.';
$string['error:usernotincourse'] = 'O usuário selecionado não é um usuário matriculado ativo neste curso.';
$string['finalgrade'] = 'Nota final';
$string['finding:adjustment'] = 'Há um ajuste de nota ativo: multiplicador {$a->mult}, deslocamento {$a->plus}.';
$string['finding:emptycategory'] = 'A categoria não possui itens de nota diretos nem subcategorias.';
$string['finding:formulainvalid'] = 'A validação da fórmula pelo Moodle falhou: {$a}';
$string['finding:formulamissingreference'] = 'A fórmula armazenada referencia o item de nota #{$a}, mas esse item não existe neste curso.';
$string['finding:formulaselfreference'] = 'A fórmula armazenada contém referência ao próprio ID do item de nota.';
$string['finding:formulaunresolved'] = 'A fórmula armazenada ainda contém uma referência no formato [[idnumber]] não resolvida.';
$string['finding:hiddenbutused'] = 'Este item está oculto e o Moodle o registra como usado ou crédito extra em {$a} agregação(ões) de usuários.';
$string['finding:invalidrange'] = 'A faixa numérica da nota é incomum: mínimo {$a->min}, máximo {$a->max}.';
$string['finding:itemlocked'] = 'O item de nota está bloqueado no nível do item.';
$string['finding:keepanddrop'] = 'A categoria mantém os {$a->keep} maior(es) item(ns) e também descarta os {$a->drop} menor(es). Revise se as duas regras são intencionais.';
$string['finding:lockedgrades'] = '{$a} nota(s) de usuário estão bloqueadas neste item.';
$string['finding:manualweights'] = '{$a->count} item(ns) possuem pesos manuais na agregação Natural. A soma armazenada dos pesos manuais é {$a->sum}; IDs com peso zero: {$a->zero}.';
$string['finding:needsupdate'] = 'O Moodle marca este item como necessitando de recálculo. O plugin não força um recálculo automaticamente.';
$string['finding:nofinalgrades'] = 'Não existem notas finais não nulas para este item. Isso pode ser esperado em uma atividade nova ou ainda não utilizada.';
$string['finding:overrides'] = '{$a} nota(s) de usuário estão sobrescritas manualmente neste item.';
$string['finding:weightconcentration'] = 'O Moodle registra peso efetivo de {$a->weight} para "{$a->item}" em pelo menos uma agregação cuja categoria possui três ou mais contribuintes.';
$string['flag:excluded'] = 'Excluído';
$string['flag:extracredit'] = 'Crédito extra';
$string['flag:hidden'] = 'Oculto';
$string['flag:locked'] = 'Bloqueado';
$string['flag:needsupdate'] = 'Requer recálculo';
$string['flag:overridden'] = 'Sobrescrito';
$string['flags'] = 'Sinalizadores';
$string['formula'] = 'Fórmula';
$string['formula:dependency'] = 'Entrada da fórmula: {$a}';
$string['gradebookdoctor:view'] = 'Usar o Médico do livro de notas';
$string['intro'] = 'Inspecione primeiro os valores calculados pelo próprio Moodle e use a IA somente para explicar esses fatos.';
$string['item'] = 'Item';
$string['nodifferences'] = 'Nenhuma diferença foi encontrada nos fatos do livro de notas para o item comparado.';
$string['nofindings'] = 'Nenhuma configuração suspeita foi encontrada pelas verificações determinísticas desta versão.';
$string['noitems'] = 'Não há itens de nota disponíveis neste curso.';
$string['nousers'] = 'Não há usuários matriculados disponíveis para este relatório.';
$string['pluginname'] = 'Médico do livro de notas';
$string['privacy:metadata'] = 'O Médico do livro de notas não armazena dados pessoais, prompts ou respostas da IA. Os dados do livro de notas são lidos somente durante a geração de um relatório autorizado.';
$string['range'] = 'Faixa';
$string['rawgrade'] = 'Nota bruta';
$string['result:explainfor'] = 'Cálculo de {$a}';
$string['run:compare'] = 'Comparar totais';
$string['run:diagnose'] = 'Executar diagnóstico';
$string['run:explain'] = 'Explicar nota';
$string['section:aidiagnosis'] = 'Explicação dos achados por IA';
$string['section:aiexplanation'] = 'Explicação por IA';
$string['section:comparison'] = 'Comparação Moodle';
$string['section:deterministicdiagnosis'] = 'Diagnóstico determinístico';
$string['section:moodlecalculation'] = 'Cálculo Moodle';
$string['selectitem'] = 'Item de nota ou total';
$string['selectuser'] = 'Aluno';
$string['selectusera'] = 'Aluno A';
$string['selectuserb'] = 'Aluno B';
$string['status'] = 'Uso no total pai';
$string['status:notrecorded'] = 'Não registrado';
$string['tab:compare'] = 'Comparar dois alunos';
$string['tab:diagnose'] = 'Diagnosticar livro de notas';
$string['tab:explain'] = 'Explique esta nota';
$string['type'] = 'Tipo';
$string['unnameditem'] = 'Item de nota #{$a}';
$string['weight'] = 'Peso efetivo';
